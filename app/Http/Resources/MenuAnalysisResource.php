<?php

namespace App\Http\Resources;

use App\Models\MenuAnalysis;
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
        if ($this->resource instanceof MenuAnalysis) {
            return $this->resource->uuid;
        }

        return $this->resource['id'];
    }

    public function toAttributes(Request $request): array
    {
        if ($this->resource instanceof MenuAnalysis) {
            return $this->asyncAttributes();
        }

        return $this->syncAttributes();
    }

    private function asyncAttributes(): array
    {
        /** @var MenuAnalysis $analysis */
        $analysis = $this->resource;
        $menu = $analysis->result_menu_data ?? [];

        return [
            'status' => $analysis->status->value,
            'image_count' => $analysis->image_count,
            'item_count' => $analysis->result_item_count ?? MenuJson::dishCount($menu),
            'menu' => $menu,
            'saved_menu_id' => $analysis->result_menu_id,
            'error_message' => $analysis->error_message,
            'started_at' => $analysis->started_at?->toIso8601String(),
            'completed_at' => $analysis->completed_at?->toIso8601String(),
            'analyzed_at' => $analysis->completed_at?->toIso8601String() ?? $analysis->created_at->toIso8601String(),
        ];
    }

    private function syncAttributes(): array
    {
        $menu = $this->resource['menu'] ?? [];
        if (! is_array($menu)) {
            $menu = [];
        }

        return [
            'status' => 'completed',
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
