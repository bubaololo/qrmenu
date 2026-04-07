<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login.
     *
     * Start a session for the given credentials. Before calling this endpoint
     * the client must fetch `GET /sanctum/csrf-cookie` to initialise CSRF
     * protection and obtain the `XSRF-TOKEN` cookie.
     *
     * @operationId login
     * @tags Auth
     * @unauthenticated
     *
     * @response 200 {
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com"
     *   }
     * }
     * @response 422 scenario="Invalid credentials" {
     *   "message": "The provided credentials are incorrect.",
     *   "errors": { "email": ["The provided credentials are incorrect."] }
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $request->user()->only('id', 'name', 'email'),
        ]);
    }

    /**
     * Send password reset link.
     *
     * Send an email with a password reset link to the given address.
     * Always returns 204 to prevent user enumeration.
     *
     * @operationId forgotPassword
     * @tags Auth
     * @unauthenticated
     *
     * @response 204 description="Reset link sent (if the email exists)"
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        return response()->json(null, 204);
    }

    /**
     * Reset password.
     *
     * Exchange a valid reset token (from the email link) for a new password.
     *
     * @operationId resetPassword
     * @tags Auth
     * @unauthenticated
     *
     * @response 204 description="Password reset successfully"
     * @response 422 scenario="Invalid or expired token" {
     *   "message": "This password reset token is invalid.",
     *   "errors": { "token": ["This password reset token is invalid."] }
     * }
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password): void {
                $user->forceFill(['password' => Hash::make($password)])
                    ->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'token' => [__($status)],
            ]);
        }

        return response()->json(null, 204);
    }

    /**
     * Change password.
     *
     * Verify the current password and set a new one.
     *
     * @operationId changePassword
     * @tags Auth
     *
     * @response 204 description="Password changed"
     * @response 422 scenario="Wrong current password" {
     *   "message": "The current password is incorrect.",
     *   "errors": { "current_password": ["The current password is incorrect."] }
     * }
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($request->current_password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json(null, 204);
    }

    /**
     * Logout.
     *
     * Invalidate the current session.
     *
     * @operationId logout
     * @tags Auth
     *
     * @response 204 description="Session invalidated"
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    /**
     * Current user.
     *
     * Return the currently authenticated user.
     *
     * @operationId currentUser
     * @tags Auth
     *
     * @response 200 {
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com"
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only('id', 'name', 'email'),
        ]);
    }
}
