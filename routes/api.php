<?php

use App\Http\Controllers\DiningTables\DiningTableController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\MenuAnalysisController;
use App\Http\Controllers\Menus\MenuController;
use App\Http\Controllers\Menus\MenuItemController;
use App\Http\Controllers\Menus\MenuOptionGroupController;
use App\Http\Controllers\Menus\MenuOptionGroupOptionController;
use App\Http\Controllers\Menus\MenuSectionController;
use App\Http\Controllers\Menus\MenuTranslationController;
use App\Http\Controllers\Restaurants\RestaurantController;
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
    Route::get('/user', fn (Request $request) => response()->json([
        'user' => $request->user()->only('id', 'name', 'email'),
    ]))->name('api.auth.user');
});

/*
|--------------------------------------------------------------------------
| Protected API (auth:sanctum — session cookie or Bearer token)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::post('/menu-analyses', [MenuAnalysisController::class, 'store']);
    Route::get('/menu-analyses/{uuid}', [MenuAnalysisController::class, 'show']);

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
    Route::post('/menus/{menu}/activate', [MenuController::class, 'activate']);
    Route::post('/menus/{menu}/clone', [MenuController::class, 'clone']);
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

    // Menu Option Groups
    Route::post('/menu-sections/{menuSection}/option-groups', [MenuOptionGroupController::class, 'store']);
    Route::put('/menu-option-groups/{menuOptionGroup}', [MenuOptionGroupController::class, 'update']);
    Route::delete('/menu-option-groups/{menuOptionGroup}', [MenuOptionGroupController::class, 'destroy']);
    Route::post('/menu-option-groups/{menuOptionGroup}/attach-items', [MenuOptionGroupController::class, 'attachItems']);
    Route::post('/menu-option-groups/{menuOptionGroup}/detach-items', [MenuOptionGroupController::class, 'detachItems']);

    // Menu Option Group Options
    Route::post('/menu-option-groups/{menuOptionGroup}/options', [MenuOptionGroupOptionController::class, 'store']);
    Route::put('/menu-option-group-options/{menuOptionGroupOption}', [MenuOptionGroupOptionController::class, 'update']);
    Route::delete('/menu-option-group-options/{menuOptionGroupOption}', [MenuOptionGroupOptionController::class, 'destroy']);

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
