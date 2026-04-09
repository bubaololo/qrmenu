<?php

namespace App\Providers;

use App\Sentinel\HorizonSentinelDriver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Laravel\Sentinel\SentinelManager;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->callAfterResolving(SentinelManager::class, function (SentinelManager $manager): void {
            $manager->extend('horizon', function () {
                return new HorizonSentinelDriver(fn () => $this->getContainer());
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?Authenticatable $user = null): bool {
            if (app()->environment('local')) {
                return true;
            }

            if ($user === null) {
                return false;
            }

            $allowed = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('HORIZON_ALLOWED_EMAILS', '')),
            )));

            if ($allowed === []) {
                return true;
            }

            return $user->email !== null && in_array($user->email, $allowed, true);
        });
    }
}
