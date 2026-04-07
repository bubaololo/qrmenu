<?php

namespace App\Providers;

use App\Models\Restaurant;
use App\Observers\RestaurantObserver;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (
            ! app()->isLocal()
            && str_starts_with((string) config('app.url'), 'https://')
        ) {
            URL::forceScheme('https');
        }

        Restaurant::observe(RestaurantObserver::class);

        if (! Storage::disk('local')->exists('livewire-tmp')) {
            Storage::disk('local')->makeDirectory('livewire-tmp');
        }

        Scramble::configure()
            ->routes(fn (Route $route) => str_starts_with($route->uri, 'api/'))
            ->afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi): void {
                $openApi->components->addSecurityScheme(
                    'session',
                    \Dedoc\Scramble\Support\Generator\SecurityScheme::apiKey('cookie', 'laravel_session'),
                );

                $openApi->info->description = <<<'MD'
## Authentication — SPA (session-based)

This API uses **Sanctum SPA authentication** via session cookie. No Bearer token required.

### Flow

1. `GET /sanctum/csrf-cookie` — initialise CSRF protection; the browser receives an
   `XSRF-TOKEN` cookie which must be echoed back as the `X-XSRF-TOKEN` request header.
2. `POST /api/v1/auth/login` — authenticate with `email` + `password`;
   on success the server sets a `laravel_session` cookie.
3. All subsequent requests are authenticated automatically via the session cookie.
4. `POST /api/v1/auth/logout` — invalidate the session.
MD;
            });
    }
}
