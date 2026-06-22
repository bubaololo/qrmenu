<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * OAuth login for social providers (Google, Zalo). Runs on the web group so the
 * OAuth `state` survives the round-trip in the session and the callback can set
 * the shared-domain session cookie the SPA reads. The provider segment is
 * constrained to the allow-list in the route definition.
 */
class SocialAuthController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->away($this->frontend("/login?error={$provider}"));
        }

        $email = $social->getEmail();

        // Google supplies an email, so match on it; Zalo accounts have none, so
        // match on the provider identity instead.
        $user = $email
            ? User::where('email', $email)->first()
            : User::where('provider', $provider)->where('provider_id', $social->getId())->first();

        if ($user) {
            $user->forceFill([
                'provider' => $provider,
                'provider_id' => $social->getId(),
                'avatar' => $social->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = new User;
            $user->forceFill([
                'name' => $social->getName() ?: ($social->getNickname() ?: $this->fallbackName($provider, $social->getId())),
                // A non-null, unique placeholder when the provider gives no email.
                'email' => $email ?: "{$provider}_{$social->getId()}@{$provider}.local",
                'provider' => $provider,
                'provider_id' => $social->getId(),
                'avatar' => $social->getAvatar(),
                'email_verified_at' => now(),
            ])->save();
        }

        Auth::guard('web')->login($user, remember: true);

        return redirect()->away($this->frontend('/'));
    }

    private function fallbackName(string $provider, string $id): string
    {
        return ucfirst($provider).' user '.substr($id, -6);
    }

    private function frontend(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
