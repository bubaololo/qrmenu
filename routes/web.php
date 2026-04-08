<?php

use App\Actions\AnalyzeMenuImageAction;
use App\Http\Controllers\MenuPageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-menu', fn () => view('test-menu'))->name('test-menu');

Route::get('/menu/{restaurant}/{lang?}', [MenuPageController::class, 'show'])->name('menu.public');

Route::post('/test-menu', function (Request $request) {
    $request->validate(['image_url' => ['required', 'url']]);

    try {
        $raw = app(AnalyzeMenuImageAction::class)->handle($request->input('image_url'));

        $clean = trim(preg_replace('/^```json\s*|\s*```$/s', '', $raw));
        $items = json_decode($clean, true) ?? [];
    } catch (Throwable $e) {
        return view('test-menu', ['error' => $e->getMessage()]);
    }

    return view('test-menu', ['items' => $items]);
})->name('test-menu');
