<?php

namespace App\Auth\Confirmation;

use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/** Confirm with an authenticator-app code — available once 2FA is confirmed. */
class TotpMethod implements ConfirmationMethod
{
    public function __construct(private TwoFactorAuthenticationProvider $provider) {}

    public function key(): string
    {
        return 'totp';
    }

    public function availableFor(User $user): bool
    {
        return ! is_null($user->two_factor_confirmed_at) && ! is_null($user->two_factor_secret);
    }

    public function requiresChallenge(): bool
    {
        return false;
    }

    public function send(User $user): void
    {
        // The code lives in the user's authenticator app — nothing to dispatch.
    }

    public function verify(User $user, string $value): bool
    {
        if (is_null($user->two_factor_secret)) {
            return false;
        }

        return $this->provider->verify(decrypt($user->two_factor_secret), $value);
    }
}
