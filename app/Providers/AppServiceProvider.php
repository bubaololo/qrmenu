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

        $adminEmails = array_filter(explode(',', env('ADMIN_EMAILS', '')));

        Gate::define('viewHorizon', fn (User $user) => app()->isLocal() || in_array($user->email, $adminEmails));
        Gate::define('viewPulse', fn (?User $user) => app()->isLocal() || ($user && in_array($user->email, $adminEmails)));
    }
}
