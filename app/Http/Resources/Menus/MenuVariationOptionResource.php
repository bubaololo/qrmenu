<?php

namespace App\Http\Resources\Menus;

use App\Models\MenuVariationOption;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin MenuVariationOption
 */
class MenuVariationOptionResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'price',
        'is_default',
        'sort_order',
    ];
}
