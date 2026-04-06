<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AnalyzeMenuImageAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\MenuAnalysisResource;
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

        return new MenuAnalysisResource([
            'id' => Str::uuid()->toString(),
            'image_count' => count($paths),
            'menu' => $menu,
            'item_count' => MenuJson::dishCount($menu),
            'llm_raw_text' => $raw,
            'llm_duration_ms' => $llmDurationMs,
            'analyzed_at' => now()->toIso8601String(),
        ]);
    }
}
