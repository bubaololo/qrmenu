<?php

namespace App\Http\Resources\DiningTables;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class DiningTableResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
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
}
