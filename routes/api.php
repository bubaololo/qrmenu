<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MenuAnalysisController;
use App\Http\Controllers\Api\V1\RestaurantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth (SPA session-based)
|--------------------------------------------------------------------------
| Step 1: GET /sanctum/csrf-cookie  — initialise CSRF (built-in Sanctum route)
| Step 2: POST /api/v1/auth/login   — authenticate, receive session cookie
| Step 3: Use session cookie on all subsequent requests
*/
Route::prefix('v1/auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.auth.forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.auth.reset-password');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/user', [AuthController::class, 'user'])->name('api.auth.user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::put('/password', [AuthController::class, 'changePassword'])->name('api.auth.password');
    });
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
});
