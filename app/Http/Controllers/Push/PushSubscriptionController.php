<?php

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Http\Requests\Push\StorePushSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Web-push subscription endpoints for the PWA. The subscription object is minted
 * by the browser's PushManager and stored against the authenticated user, who is
 * later reachable via `$user->notify(...)` over the WebPush channel.
 */
class PushSubscriptionController extends Controller
{
    /**
     * Store (or refresh) the current user's push subscription.
     */
    public function store(StorePushSubscriptionRequest $request): Response
    {
        $data = $request->validated();

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['key'],
            $data['token'],
            $data['encoding'] ?? null,
        );

        return response()->noContent();
    }

    /**
     * Remove a push subscription by endpoint (browser unsubscribed / opted out).
     */
    public function destroy(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->noContent();
    }

    /**
     * Expose the VAPID public key so the SPA can build its applicationServerKey.
     * Public on purpose — it is a public key — and fetched (rather than baked into
     * a VITE_ var) so rotating it never requires a frontend rebuild.
     *
     * @response array{public_key: string|null}
     */
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'public_key' => config('webpush.vapid.public_key'),
        ]);
    }
}
