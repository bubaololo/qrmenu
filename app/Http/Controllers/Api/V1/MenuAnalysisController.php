<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AnalyzeMenuImageAction;
use App\Actions\SaveMenuAnalysisAction;
use App\Enums\RestaurantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\MenuAnalysisResource;
use App\Models\RestaurantUser;
use App\Support\MenuJson;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuAnalysisController extends Controller
{
    public function store(Request $request, AnalyzeMenuImageAction $action): MenuAnalysisResource
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:10240'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
        ]);

        $paths = [];
        foreach ($request->file('images') as $file) {
            $paths[] = $file->store('menu-analyzer-uploads', 'public');
        }

        $llmStarted = microtime(true);
        $raw = $action->handle($paths);
        $llmDurationMs = (int) round((microtime(true) - $llmStarted) * 1000);

        /** @var array<string, mixed> $menu */
        $menu = MenuJson::decodeMenuFromLlmText($raw);

        $savedMenuId = null;
        $restaurantId = $request->integer('restaurant_id') ?: null;

        if ($restaurantId !== null) {
            $isOwner = RestaurantUser::where('restaurant_id', $restaurantId)
                ->where('user_id', $request->user()->id)
                ->where('role', RestaurantUserRole::Owner->value)
                ->exists();

            if ($isOwner) {
                $savedMenu = app(SaveMenuAnalysisAction::class)->handle($menu, $restaurantId, count($paths));
                $savedMenuId = $savedMenu->id;
            }
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
