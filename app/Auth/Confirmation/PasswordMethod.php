<?php

namespace App\Auth\Confirmation;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Confirm with the account's local password — only available if one is set. */
class PasswordMethod implements ConfirmationMethod
{
    public function key(): string
    {
        return 'password';
    }

    public function availableFor(User $user): bool
    {
        return ! is_null($user->password);
    }

    public function requiresChallenge(): bool
    {
        return false;
    }

    public function send(User $user): void
    {
        // No challenge to dispatch.
    }

    public function verify(User $user, string $value): bool
    {
        return ! is_null($user->password) && Hash::check($value, $user->password);
    }
}
