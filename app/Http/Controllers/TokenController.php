<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * Issue an API token.
     *
     * Authenticate with email and password and receive a Bearer token
     * to use in the `Authorization` header for all subsequent requests.
     *
     * @operationId createToken
     *
     * @tags Authentication
     *
     * @unauthenticated
     *
     * @response 200 {
     *   "token": "1|abc123…",
     *   "token_type": "Bearer"
     * }
     * @response 422 scenario="Invalid credentials" {
     *   "message": "The provided credentials are incorrect.",
     *   "errors": { "email": ["The provided credentials are incorrect."] }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Revoke the current token.
     *
     * Deletes the token used to make this request. After this call
     * the token can no longer be used for authentication.
     *
     * @operationId revokeToken
     *
     * @tags Authentication
     *
     * @response 204 description="Token revoked successfully"
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
