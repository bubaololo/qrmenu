<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List all users (id + email) for the admin-only push test harness.
     *
     * @response array{data: array<int, array{id: int, email: string}>}
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);

        $users = User::query()
            ->orderBy('email')
            ->get(['id', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'email' => $user->email,
            ])
            ->all();

        return response()->json(['data' => $users]);
    }
}
