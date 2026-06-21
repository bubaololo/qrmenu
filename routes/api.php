<?php

use App\Http\Controllers\DiningTables\DiningTableController;
use App\Http\Controllers\IconController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\MenuAnalysisController;
use App\Http\Controllers\Menus\MenuController;
use App\Http\Controllers\Menus\MenuItemController;
use App\Http\Controllers\Menus\MenuSectionController;
use App\Http\Controllers\Menus\MenuTranslationController;
use App\Http\Controllers\Menus\ModifierGroupController;
use App\Http\Controllers\Menus\ModifierOptionController;
use App\Http\Controllers\Orders\BillController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Orders\OrderItemController;
use App\Http\Controllers\Public\PublicOrderController;
use App\Http\Controllers\Push\PushSubscriptionController;
use App\Http\Controllers\Push\PushTestController;
use App\Http\Controllers\Restaurants\RestaurantController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Zones\ZoneController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
| Fortify handles: login, register, logout, forgot/reset password,
| update password — all under /api/v1/auth prefix (see config/fortify.php).
|
| SPA flow:
|   1. GET  /sanctum/csrf-cookie     — init CSRF
|   2. POST /api/v1/auth/login       — authenticate (Fortify)
|   3. Use session cookie on subsequent requests
*/
Route::prefix('v1/auth')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_enabled' => ! is_null($user->two_factor_confirmed_at),
            ],
        ]);
    })->name('api.auth.user');
});

/*
|--------------------------------------------------------------------------
| Protected API (auth:sanctum — session cookie or Bearer token)
|--------------------------------------------------------------------------
*/
// Public Orders — guests place orders by table_uniqid + restaurant_uniqid.
Route::prefix('v1/public')->middleware('throttle:30,1')->group(function (): void {
    Route::post('/orders', [PublicOrderController::class, 'store'])->name('public.orders.store');
    Route::get('/orders/{guestToken}', [PublicOrderController::class, 'show'])
        ->where('guestToken', '[0-9a-fA-F-]{36}')
        ->name('public.orders.show');
    Route::get('/icons', [IconController::class, 'index'])->name('public.icons.index');
});

// VAPID public key — needed by the SPA before the user is authenticated, and a
// public key by definition, so it lives outside the auth group.
Route::get('/v1/push/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey'])
    ->name('push.vapid');

Route::prefix('v1')->middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::post('/menu-analyses', [MenuAnalysisController::class, 'store']);
    Route::get('/menu-analyses/{uuid}', [MenuAnalysisController::class, 'show']);

    // Web Push — PWA subscribe/unsubscribe; admin-only test sender + user list.
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store']);
    Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy']);
    Route::post('/push/test', [PushTestController::class, 'send']);
    Route::get('/users', [UserController::class, 'index']);

    // Restaurants
    Route::get('/restaurants', [RestaurantController::class, 'index']);
    Route::post('/restaurants', [RestaurantController::class, 'store']);
    Route::get('/restaurants/active-menus', [RestaurantController::class, 'activeMenus']);
    Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show']);
    Route::put('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
    Route::delete('/restaurants/{restaurant}', [RestaurantController::class, 'destroy']);
    Route::get('/restaurants/{restaurant}/qr', [RestaurantController::class, 'qr']);

    // Zones
    Route::get('/restaurants/{restaurant}/zones', [ZoneController::class, 'index']);
    Route::post('/restaurants/{restaurant}/zones', [ZoneController::class, 'store']);
    Route::get('/zones/{zone}', [ZoneController::class, 'show']);
    Route::put('/zones/{zone}', [ZoneController::class, 'update']);
    Route::delete('/zones/{zone}', [ZoneController::class, 'destroy']);

    // Dining Tables
    Route::get('/zones/{zone}/tables', [DiningTableController::class, 'index']);
    Route::post('/zones/{zone}/tables', [DiningTableController::class, 'store']);
    Route::get('/dining-tables/{diningTable}', [DiningTableController::class, 'show']);
    Route::put('/dining-tables/{diningTable}', [DiningTableController::class, 'update']);
    Route::delete('/dining-tables/{diningTable}', [DiningTableController::class, 'destroy']);
    Route::get('/dining-tables/{diningTable}/qr', [DiningTableController::class, 'qr']);

    // Menus
    Route::get('/restaurants/{restaurant}/menus', [MenuController::class, 'index']);
    Route::post('/restaurants/{restaurant}/menus', [MenuController::class, 'store']);
    Route::get('/menus/{menu}', [MenuController::class, 'full']);
    Route::put('/menus/{menu}', [MenuController::class, 'update']);
    Route::delete('/menus/{menu}', [MenuController::class, 'destroy']);
    Route::get('/menus/{menu}/search', [MenuController::class, 'search']);
    Route::get('/menus/{menu}/locales', [MenuTranslationController::class, 'locales']);
    Route::post('/menus/{menu}/translations/{locale}', [MenuTranslationController::class, 'store']);

    // Menu Sections
    Route::post('/menus/{menu}/sections', [MenuSectionController::class, 'store']);
    Route::put('/menus/{menu}/sections/reorder', [MenuSectionController::class, 'reorder']);
    Route::put('/menu-sections/{menuSection}', [MenuSectionController::class, 'update']);
    Route::delete('/menu-sections/{menuSection}', [MenuSectionController::class, 'destroy']);

    // Menu Items
    Route::post('/menu-sections/{menuSection}/items', [MenuItemController::class, 'store']);
    Route::put('/menu-sections/{menuSection}/items/reorder', [MenuItemController::class, 'reorder']);
    Route::put('/menu-items/{menuItem}', [MenuItemController::class, 'update']);
    Route::delete('/menu-items/{menuItem}', [MenuItemController::class, 'destroy']);
    Route::post('/menu-items/{menuItem}/clone', [MenuItemController::class, 'clone']);

    // Modifier groups (Size / Extras / …) — shared across the menu. A group's
    // pricing_mode decides whether its option price replaces the base or adds.
    Route::get('/menus/{menu}/modifier-groups', [ModifierGroupController::class, 'index']);
    Route::post('/menus/{menu}/modifier-groups', [ModifierGroupController::class, 'store']);
    Route::put('/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'update']);
    Route::delete('/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'destroy']);
    Route::post('/modifier-groups/{modifierGroup}/attach-items', [ModifierGroupController::class, 'attachItems']);
    Route::post('/modifier-groups/{modifierGroup}/detach-items', [ModifierGroupController::class, 'detachItems']);
    // Per-item override of an attached group's selection rule (pivot only).
    Route::put('/menu-items/{menuItem}/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'updateItemOverrides']);

    // Modifier options (choices within a group)
    Route::post('/modifier-groups/{modifierGroup}/options', [ModifierOptionController::class, 'store']);
    Route::put('/modifier-options/{modifierOption}', [ModifierOptionController::class, 'update']);
    Route::delete('/modifier-options/{modifierOption}', [ModifierOptionController::class, 'destroy']);

    // Orders
    Route::get('/restaurants/{restaurant}/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}', [OrderController::class, 'update']);
    Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
    Route::patch('/order-items/{orderItem}', [OrderItemController::class, 'update']);

    // Bills
    Route::get('/restaurants/{restaurant}/bills', [BillController::class, 'index']);
    Route::get('/bills/{bill}', [BillController::class, 'show']);
    Route::post('/bills/{bill}/close', [BillController::class, 'close']);
    Route::post('/bills/{bill}/split', [BillController::class, 'split']);

    // Images
    Route::post('/restaurants/{restaurantId}/image', [ImageController::class, 'updateRestaurant']);
    Route::delete('/restaurants/{restaurantId}/image', [ImageController::class, 'deleteRestaurant']);
    Route::post('/restaurants/{restaurantId}/logo', [ImageController::class, 'updateRestaurantLogo']);
    Route::delete('/restaurants/{restaurantId}/logo', [ImageController::class, 'deleteRestaurantLogo']);
    Route::post('/zones/{zoneId}/image', [ImageController::class, 'updateZone']);
    Route::delete('/zones/{zoneId}/image', [ImageController::class, 'deleteZone']);
    Route::post('/menu-items/{itemId}/image', [ImageController::class, 'updateMenuItem']);
    Route::delete('/menu-items/{itemId}/image', [ImageController::class, 'deleteMenuItem']);
});
