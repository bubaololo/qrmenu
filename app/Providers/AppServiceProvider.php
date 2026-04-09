<?php

namespace App\Providers;

use App\Models\Restaurant;
use App\Observers\RestaurantObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Restaurant::observe(RestaurantObserver::class);
    }
}
