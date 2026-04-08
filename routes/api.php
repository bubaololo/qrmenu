<?php

use App\Http\Controllers\ImageController;
use App\Http\Controllers\MenuAnalysisController;
use App\Http\Controllers\RestaurantController;
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

    Route::get('/restaurants', [RestaurantController::class, 'index']);
    Route::post('/restaurants', [RestaurantController::class, 'store']);
    Route::get('/restaurants/active-menus', [RestaurantController::class, 'activeMenus']);
    Route::post('/restaurants/{restaurantId}/image', [ImageController::class, 'updateRestaurant']);
    Route::delete('/restaurants/{restaurantId}/image', [ImageController::class, 'deleteRestaurant']);

    Route::post('/menu-items/{itemId}/image', [ImageController::class, 'updateMenuItem']);
    Route::delete('/menu-items/{itemId}/image', [ImageController::class, 'deleteMenuItem']);
});
