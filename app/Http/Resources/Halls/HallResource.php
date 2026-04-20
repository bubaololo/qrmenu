<?php

namespace App\Http\Resources\Halls;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class HallResource extends JsonApiResource
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

    /** @var array<int, string> */
    public $relationships = [
        'tables',
    ];
}
