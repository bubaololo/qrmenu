<?php

namespace App\Http\Resources\Zones;

use App\Http\Resources\DiningTables\DiningTableResource;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class ZoneResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'color',
        'sort_order',
        'is_active',
        'image_url',
        'thumb_url',
    ];

    /** @var array<string, class-string> */
    public $relationships = [
        'tables' => DiningTableResource::class,
    ];
}
