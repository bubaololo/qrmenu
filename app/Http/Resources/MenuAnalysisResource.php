<?php

namespace App\Http\Resources;

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
        return [
            'image_count' => $this->resource['image_count'],
            'items' => $this->resource['items'],
            'analyzed_at' => $this->resource['analyzed_at'],
        ];
    }
}
