<?php

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Only private channels need an authorization callback. The public progress
| channels (`menu-analysis.{uuid}`, `menu-translation.{menuId}.{locale}`) are
| intentionally open — the unguessable id in the topic is the bearer token,
| and anonymous QR/wizard users must be able to subscribe without a session.
*/

// Kitchen / waiter order dashboards. Same membership check the SSE endpoint used.
Broadcast::channel('restaurant-orders.{restaurant}', function (User $user, int $restaurant): bool {
    $model = Restaurant::find($restaurant);

    return $model !== null && Gate::forUser($user)->allows('viewAny', [Order::class, $model]);
});

// Restaurant-scoped admin notifications (menu-item changes, translation runs,
// image processing). Future home for PWA web-push delivery. Any staff member
// attached to the restaurant may subscribe.
Broadcast::channel('restaurant.{restaurant}', function (User $user, int $restaurant): bool {
    return Restaurant::find($restaurant)?->users()->whereKey($user->id)->exists() ?? false;
});
