<?php

namespace App\Auth\Confirmation;

use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Registry of step-up confirmation methods. Resolves which methods a given user
 * can use and, on a successful verification, marks the session as recently
 * confirmed using the SAME marker Laravel's `password.confirm` middleware reads
 * (`auth.password_confirmed_at`) — so every route already gated by
 * `password.confirm` transparently accepts any of these methods.
 *
 * To add a channel (Zalo, SMS): implement {@see ConfirmationMethod} and add the
 * class to {@see self::METHODS}.
 */
class ConfirmationManager
{
    /** @var array<int, class-string<ConfirmationMethod>> Display order. */
    private const METHODS = [
        PasswordMethod::class,
        TotpMethod::class,
        EmailCodeMethod::class,
    ];

    /** @var array<string, ConfirmationMethod> */
    private array $methods = [];

    public function __construct(Container $container)
    {
        foreach (self::METHODS as $class) {
            $method = $container->make($class);
            $this->methods[$method->key()] = $method;
        }
    }

    /** @return array<int, ConfirmationMethod> methods usable by this user, in display order */
    public function availableFor(User $user): array
    {
        return array_values(array_filter(
            $this->methods,
            fn (ConfirmationMethod $method) => $method->availableFor($user),
        ));
    }

    public function find(string $key): ?ConfirmationMethod
    {
        return $this->methods[$key] ?? null;
    }

    /**
     * Verify a method for the user and, on success, mark the session confirmed.
     */
    public function confirm(Request $request, User $user, string $key, string $value): bool
    {
        $method = $this->find($key);

        if (! $method || ! $method->availableFor($user) || ! $method->verify($user, $value)) {
            return false;
        }

        $request->session()->put('auth.password_confirmed_at', now()->unix());

        return true;
    }
}
