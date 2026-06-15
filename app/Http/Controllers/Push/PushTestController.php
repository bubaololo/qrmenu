<?php

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Http\Requests\Push\SendTestPushRequest;
use App\Models\User;
use App\Notifications\TestPushNotification;
use Illuminate\Http\JsonResponse;

/**
 * Admin-only harness to fire an ad-hoc web push at a chosen user. Stand-in for
 * the (not-yet-built) order notifications, which will reuse the same
 * `$user->notify(new ...)` path over the WebPush channel.
 */
class PushTestController extends Controller
{
    public function send(SendTestPushRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var User $target */
        $target = User::findOrFail($data['user_id']);

        $subscriptionCount = $target->pushSubscriptions()->count();

        $target->notify(new TestPushNotification(
            title: $data['title'] ?? 'Test notification',
            body: $data['body'],
        ));

        return response()->json([
            'subscriptions' => $subscriptionCount,
            'message' => $subscriptionCount === 0
                ? 'Queued, but the user has no push subscriptions yet (enable notifications in the PWA first).'
                : "Queued to {$subscriptionCount} subscription(s).",
        ]);
    }
}
