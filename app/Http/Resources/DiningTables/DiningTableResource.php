<?php

namespace App\Http\Resources\DiningTables;

use App\Actions\BuildPublicMenuUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class DiningTableResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'uniqid',
        'number',
        'capacity',
        'shape',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<int|string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            ...$this->attributes,
            'menu_url' => app(BuildPublicMenuUrl::class)->forTable($this->resource),
        ];
    }
}
