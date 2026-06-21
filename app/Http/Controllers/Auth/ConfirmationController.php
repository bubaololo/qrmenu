<?php

namespace App\Http\Controllers\Auth;

use App\Auth\Confirmation\ConfirmationManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Step-up confirmation endpoints. A successful confirm marks the session via
 * `auth.password_confirmed_at`, satisfying the `password.confirm` middleware for
 * the gated action that follows.
 */
class ConfirmationController extends Controller
{
    public function __construct(private ConfirmationManager $manager) {}

    /** Methods this user can confirm with. */
    public function methods(Request $request): JsonResponse
    {
        $methods = array_map(
            fn ($method) => [
                'key' => $method->key(),
                'requires_challenge' => $method->requiresChallenge(),
            ],
            $this->manager->availableFor($request->user()),
        );

        return response()->json(['methods' => $methods]);
    }

    /** Dispatch the one-time code for a challenge method (email, …). */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['method' => ['required', 'string']]);
        $user = $request->user();
        $method = $this->manager->find($data['method']);

        if (! $method || ! $method->availableFor($user) || ! $method->requiresChallenge()) {
            throw ValidationException::withMessages(['method' => 'This confirmation method is unavailable.']);
        }

        $key = "confirm-send:{$user->id}:{$data['method']}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['method' => 'Too many requests. Please wait before trying again.']);
        }

        RateLimiter::hit($key, 600);
        $method->send($user);

        return response()->json(null, 202);
    }

    /** Verify a value and mark the session confirmed. */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string'],
            'value' => ['required', 'string'],
        ]);
        $user = $request->user();

        $throttle = "confirm-verify:{$user->id}";

        if (RateLimiter::tooManyAttempts($throttle, 10)) {
            throw ValidationException::withMessages(['value' => 'Too many attempts. Please wait before trying again.']);
        }

        if (! $this->manager->confirm($request, $user, $data['method'], $data['value'])) {
            RateLimiter::hit($throttle, 600);

            throw ValidationException::withMessages(['value' => 'Confirmation failed. Check the value and try again.']);
        }

        RateLimiter::clear($throttle);

        return response()->json(null, 204);
    }
}
