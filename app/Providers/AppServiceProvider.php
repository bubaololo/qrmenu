<?php

namespace App\Providers;

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
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        if (! Storage::disk('local')->exists('livewire-tmp')) {
            Storage::disk('local')->makeDirectory('livewire-tmp');
        }

        Scramble::configure()
            ->routes(fn (Route $route) => str_starts_with($route->uri, 'api/'));
    }
}
