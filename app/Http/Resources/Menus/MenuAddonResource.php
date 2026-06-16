<?php

namespace App\Http\Resources\Menus;

use App\Models\MenuAddon;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin MenuAddon
 */
class MenuAddonResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'price',
        'sort_order',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'items',
    ];
}
