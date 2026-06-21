<?php

namespace App\Providers;

use App\Models\Icon;
use App\Models\Menu;
use App\Models\MenuAnalysis;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\User;
use App\Models\Zone;
use App\Observers\IconObserver;
use App\Observers\MenuAnalysisObserver;
use App\Observers\MenuItemObserver;
use App\Observers\MenuObserver;
use App\Observers\MenuSectionObserver;
use App\Observers\RestaurantObserver;
use App\Observers\TranslationObserver;
use App\Observers\ZoneObserver;
use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Make the API docs (Scramble) public: drop the RestrictedDocsAccess
        // gate, keep only the `web` middleware. Set here in register() so it
        // lands before Scramble reads config('scramble') while booting.
        config(['scramble.middleware' => ['web']]);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        // Scramble's API docs default to /docs/api, which LaRecipe's
        // /docs/{version} catch-all shadows. Move them out from under /docs.
        Scramble::configure()->expose(ui: 'api/docs', document: 'api/docs.json');

        // Password reset emails must link into the SPA, not the API. Fortify
        // exposes only the JSON reset endpoint; the email link points the user
        // at the frontend form, which then POSTs back to /reset-password.
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return rtrim((string) config('app.frontend_url'), '/')."/reset-password?token={$token}&email={$email}";
        });

        // Fortify dispatches Registered after sign-up; send the verification email.
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Point the verification email at our own signature-validated route
        // instead of Fortify's auth-guarded one — the link is opened from an
        // email (no SPA session/Referer), so it must verify without a session.
        VerifyEmail::createUrlUsing(function (object $notifiable): string {
            return URL::temporarySignedRoute(
                'spa.verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
            );
        });

        Restaurant::observe(RestaurantObserver::class);
        Menu::observe(MenuObserver::class);
        MenuSection::observe(MenuSectionObserver::class);
        MenuItem::observe(MenuItemObserver::class);
        Zone::observe(ZoneObserver::class);
        MenuAnalysis::observe(MenuAnalysisObserver::class);
        Translation::observe(TranslationObserver::class);
        Icon::observe(IconObserver::class);

        $isAllowed = fn (?User $user) => app()->isLocal() || ($user?->isAdmin() ?? false);

        Gate::define('viewHorizon', $isAllowed);
        Gate::define('viewPulse', $isAllowed);
    }
}
