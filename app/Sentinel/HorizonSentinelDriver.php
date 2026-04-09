<?php

namespace App\Sentinel;

use Illuminate\Http\Request;
use Laravel\Sentinel\Drivers\Driver;

/**
 * Horizon registers SentinelMiddleware with driver "horizon". Without a custom driver,
 * Sentinel falls back to the "laravel" driver, which aborts with 401 in local/staging
 * when the client IP is public and the request passes a trusted reverse proxy.
 * Access control stays in Laravel Horizon (viewHorizon gate via Horizon::check).
 */
final class HorizonSentinelDriver extends Driver
{
    public function authorize(Request $request): bool
    {
        return true;
    }
}
