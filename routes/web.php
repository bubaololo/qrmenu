<?php

use App\Actions\AnalyzeMenuImageAction;
use App\Http\Controllers\MenuPageController;
use App\Support\FoodIcons;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/menu-sprite.svg', function (Request $request) {
    $sprite = FoodIcons::sprite();

    $response = response($sprite, 200, [
        'Content-Type' => 'image/svg+xml; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600, must-revalidate',
        'X-Content-Type-Options' => 'nosniff',
    ])->setEtag(md5($sprite));

    $response->isNotModified($request);

    return $response;
})->name('menu.sprite');

Route::get('/test-menu', fn () => view('test-menu'))->name('test-menu');

Route::get('/{restaurant}/t/{table}/{lang?}', [MenuPageController::class, 'showTable'])
    ->name('menu.public.table')
    ->where('restaurant', '[0-9]+|[a-zA-Z0-9]{8,}')
    ->where('table', '[a-zA-Z0-9]{8,}')
    ->where('lang', '[a-z]{2}');

Route::get('/{identifier}/{lang?}', [MenuPageController::class, 'show'])->name('menu.public')
    ->where('identifier', '[0-9]+|[a-zA-Z0-9]{8,}')
    ->where('lang', '[a-z]{2}');

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
