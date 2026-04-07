<?php

namespace App\Http\Resources;

use App\Support\MenuJson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class MenuAnalysisResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'menu-analyses';
    }

    public function toId(Request $request): string
    {
        return $this->resource['id'];
    }

    public function toAttributes(Request $request): array
    {
        $menu = $this->resource['menu'] ?? [];
        if (! is_array($menu)) {
            $menu = [];
        }

        return [
            'image_count' => (int) ($this->resource['image_count'] ?? 0),
            'item_count' => (int) ($this->resource['item_count'] ?? MenuJson::dishCount($menu)),
            'menu' => $menu,
            'llm_raw_text' => (string) ($this->resource['llm_raw_text'] ?? ''),
            'llm_duration_ms' => (int) ($this->resource['llm_duration_ms'] ?? 0),
            'analyzed_at' => (string) ($this->resource['analyzed_at'] ?? now()->toIso8601String()),
            'saved_menu_id' => $this->resource['saved_menu_id'] ?? null,
        ];
    }
}
