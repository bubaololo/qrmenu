<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AnalyzeMenuImageAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\MenuAnalysisResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuAnalysisController extends Controller
{
    public function store(Request $request, AnalyzeMenuImageAction $action): MenuAnalysisResource
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:20480'],
        ]);

        $paths = [];
        foreach ($request->file('images') as $file) {
            $paths[] = $file->store('menu-analyzer-uploads', 'public');
        }

        $raw = $action->handle($paths);
        $clean = trim(preg_replace('/^```json\s*|\s*```$/s', '', $raw));
        $decoded = json_decode($clean, true) ?? [];
        // LLM may return {"items": [...]} instead of a plain array
        $items = is_array($decoded) && array_is_list($decoded) ? $decoded : ($decoded['items'] ?? array_values($decoded));

        return new MenuAnalysisResource([
            'id' => Str::uuid()->toString(),
            'image_count' => count($paths),
            'items' => $items,
            'analyzed_at' => now()->toIso8601String(),
        ]);
    }
}
