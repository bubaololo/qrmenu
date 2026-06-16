<?php

namespace App\Http\Resources\Menus;

use App\Models\MenuVariation;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin MenuVariation
 */
class MenuVariationResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'sort_order',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'options',
        'items',
    ];
}
