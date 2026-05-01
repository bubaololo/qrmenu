<?php

namespace App\Providers;

use App\Models\Menu;
use App\Models\MenuOptionGroup;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\User;
use App\Observers\MenuObserver;
use App\Observers\MenuOptionGroupObserver;
use App\Observers\MenuSectionObserver;
use App\Observers\RestaurantObserver;
use App\Observers\TranslationObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Restaurant::observe(RestaurantObserver::class);
        Menu::observe(MenuObserver::class);
        MenuSection::observe(MenuSectionObserver::class);
        MenuOptionGroup::observe(MenuOptionGroupObserver::class);
        Translation::observe(TranslationObserver::class);

        $isAllowed = fn (?User $user) => app()->isLocal() || ($user?->isAdmin() ?? false);

        Gate::define('viewHorizon', $isAllowed);
        Gate::define('viewPulse', $isAllowed);
    }
}
