<?php

namespace App\Auth\Confirmation;

use App\Models\User;
use App\Notifications\ConfirmationCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Confirm with a one-time code emailed to the user. Universal — every account
 * has an email. The code is stored hashed in the cache with a short TTL and is
 * single-use.
 */
class EmailCodeMethod implements ConfirmationMethod
{
    private const TTL_SECONDS = 600;

    public function key(): string
    {
        return 'email_code';
    }

    public function availableFor(User $user): bool
    {
        return ! is_null($user->email);
    }

    public function requiresChallenge(): bool
    {
        return true;
    }

    public function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($user), Hash::make($code), self::TTL_SECONDS);

        $user->notify(new ConfirmationCode($code));
    }

    public function verify(User $user, string $value): bool
    {
        $hash = Cache::get($this->cacheKey($user));

        if (! $hash || ! Hash::check(trim($value), $hash)) {
            return false;
        }

        Cache::forget($this->cacheKey($user));

        return true;
    }

    private function cacheKey(User $user): string
    {
        return "confirm-code:{$user->id}";
    }
}
