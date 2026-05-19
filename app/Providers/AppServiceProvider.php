<?php

namespace App\Providers;

use App\Models\Icon;
use App\Models\Menu;
use App\Models\MenuAnalysis;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\User;
use App\Models\Zone;
use App\Observers\IconObserver;
use App\Observers\MenuAnalysisObserver;
use App\Observers\MenuItemObserver;
use App\Observers\MenuObserver;
use App\Observers\MenuOptionGroupObserver;
use App\Observers\MenuSectionObserver;
use App\Observers\RestaurantObserver;
use App\Observers\TranslationObserver;
use App\Observers\ZoneObserver;
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
        MenuItem::observe(MenuItemObserver::class);
        MenuOptionGroup::observe(MenuOptionGroupObserver::class);
        Zone::observe(ZoneObserver::class);
        MenuAnalysis::observe(MenuAnalysisObserver::class);
        Translation::observe(TranslationObserver::class);
        Icon::observe(IconObserver::class);

        $isAllowed = fn (?User $user) => app()->isLocal() || ($user?->isAdmin() ?? false);

        Gate::define('viewHorizon', $isAllowed);
        Gate::define('viewPulse', $isAllowed);
    }
}
