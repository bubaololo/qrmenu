<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Contracts\PasswordUpdateResponse as PasswordUpdateResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->instance(LoginResponseContract::class, new class implements LoginResponseContract
        {
            public function toResponse($request)
            {
                return response()->json(['user' => $request->user()->only('id', 'name', 'email')]);
            }
        });

        $this->app->instance(RegisterResponseContract::class, new class implements RegisterResponseContract
        {
            public function toResponse($request)
            {
                return response()->json(['user' => $request->user()->only('id', 'name', 'email')], 201);
            }
        });

        $this->app->instance(LogoutResponseContract::class, new class implements LogoutResponseContract
        {
            public function toResponse($request)
            {
                return response()->json(null, 204);
            }
        });

        $this->app->instance(PasswordResetResponseContract::class, new class implements PasswordResetResponseContract
        {
            public function toResponse($request)
            {
                return response()->json(null, 204);
            }
        });

        $this->app->instance(PasswordUpdateResponseContract::class, new class implements PasswordUpdateResponseContract
        {
            public function toResponse($request)
            {
                return response()->json(null, 204);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
