<?php

namespace App\Providers;

use App\Sentinel\HorizonSentinelDriver;
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
     * The viewHorizon gate is defined in AppServiceProvider alongside viewPulse
     * and FilamentUser::canAccessPanel, so ADMIN_EMAILS controls all three.
     * Override the parent's default (which locks Horizon down to an empty email
     * list) to a no-op so our definition survives.
     */
    protected function gate(): void
    {
        //
    }
}
