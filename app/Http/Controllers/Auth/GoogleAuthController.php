<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Send the user to Google's consent screen. Runs on the web group, so the
     * OAuth `state` is stashed in the session for the callback to validate.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google's callback: find or create the matching user, log them in on
     * the web guard (sets the shared-domain session cookie the SPA reads), then
     * bounce back to the frontend. Failures return to the login screen.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->away($this->frontend('/login?error=google'));
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            // Link the Google identity to a pre-existing (e.g. password) account.
            $user->forceFill([
                'provider' => 'google',
                'provider_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = new User;
            $user->forceFill([
                'name' => $googleUser->getName() ?: ($googleUser->getNickname() ?: $googleUser->getEmail()),
                'email' => $googleUser->getEmail(),
                'provider' => 'google',
                'provider_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ])->save();
        }

        Auth::guard('web')->login($user, remember: true);

        return redirect()->away($this->frontend('/'));
    }

    private function frontend(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
