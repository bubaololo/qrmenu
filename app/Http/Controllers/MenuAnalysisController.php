<?php

namespace App\Http\Controllers;

use App\Actions\AnalyzeMenuImageAction;
use App\Actions\SaveMenuAnalysisAction;
use App\Filament\Pages\MenuAnalyzer;
use App\Http\Resources\MenuAnalysisResource;
use App\Llm\GeminiVisionProvider;
use App\Llm\OpenRouterProvider;
use App\Models\Restaurant;
use App\Support\MenuJson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuAnalysisController extends Controller
{
    public function store(Request $request, AnalyzeMenuImageAction $action): MenuAnalysisResource
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:10240'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'model' => ['nullable', 'string', 'in:'.implode(',', array_keys(MenuAnalyzer::visionModels()))],
        ]);

        $disk = config('image.originals_disk');
        $paths = [];
        foreach ($request->file('images') as $file) {
            $paths[] = $file->store('menu-analyzer-uploads', $disk);
        }

        $provider = $request->input('model', 'gemini') === 'gemini'
            ? app(GeminiVisionProvider::class)
            : app()->makeWith(OpenRouterProvider::class, ['openRouterModel' => $request->input('model')]);

        try {
            $llmStarted = microtime(true);
            $raw = $action->handle($paths, $disk, $provider);
            $llmDurationMs = (int) round((microtime(true) - $llmStarted) * 1000);

            /** @var array<string, mixed> $menu */
            $menu = MenuJson::decodeMenuFromLlmText($raw);
        } finally {
            Storage::disk($disk)->delete($paths);
        }

        $savedMenuId = null;
        $restaurantId = $request->integer('restaurant_id') ?: null;

        if ($restaurantId !== null) {
            $restaurant = Restaurant::findOrFail($restaurantId);
            Gate::authorize('update', $restaurant);

            $savedMenu = app(SaveMenuAnalysisAction::class)->handle($menu, $restaurantId, count($paths));
            $savedMenuId = $savedMenu->id;
        }

        return new MenuAnalysisResource([
            'id' => Str::uuid()->toString(),
            'image_count' => count($paths),
            'menu' => $menu,
            'item_count' => MenuJson::dishCount($menu),
            'llm_raw_text' => $raw,
            'llm_duration_ms' => $llmDurationMs,
            'analyzed_at' => now()->toIso8601String(),
            'saved_menu_id' => $savedMenuId,
        ]);
    }
}
