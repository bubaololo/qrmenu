<?php

namespace App\Providers;

use App\Models\Restaurant;
use App\Models\User;
use App\Observers\RestaurantObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Restaurant::observe(RestaurantObserver::class);

        $isAllowed = fn (?User $user) => app()->isLocal() || ($user?->isAdmin() ?? false);

        Gate::define('viewHorizon', $isAllowed);
        Gate::define('viewPulse', $isAllowed);
    }
}
