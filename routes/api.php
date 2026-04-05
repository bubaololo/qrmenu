<?php

use App\Http\Controllers\Api\V1\MenuAnalysisController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/tokens', [TokenController::class, 'store']);
Route::delete('/v1/tokens/current', [TokenController::class, 'destroy'])->middleware('auth:sanctum');

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::post('/menu-analyses', [MenuAnalysisController::class, 'store']);
});
