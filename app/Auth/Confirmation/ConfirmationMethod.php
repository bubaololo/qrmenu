<?php

namespace App\Auth\Confirmation;

use App\Models\User;

/**
 * A way for a user to prove "it's really me" before a sensitive action
 * (step-up / re-authentication). Implementations are registered in
 * {@see ConfirmationManager}; adding a channel (e.g. Zalo, SMS) is a single new
 * class plus a line in the registry.
 */
interface ConfirmationMethod
{
    /** Stable identifier used by the API and the SPA (e.g. "password", "email_code"). */
    public function key(): string;

    /** Whether this method is usable by the given user (e.g. password set, 2FA on). */
    public function availableFor(User $user): bool;

    /** True for methods that must dispatch a one-time code first (email/SMS), false for password/TOTP. */
    public function requiresChallenge(): bool;

    /** Dispatch the challenge (send the code). No-op for non-challenge methods. */
    public function send(User $user): void;

    /** Verify the user-supplied value (password / code). */
    public function verify(User $user, string $value): bool;
}
