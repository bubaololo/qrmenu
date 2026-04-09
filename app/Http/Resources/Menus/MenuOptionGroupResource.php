<?php

namespace App\Http\Resources\Menus;

use App\Models\MenuOptionGroup;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin MenuOptionGroup
 */
class MenuOptionGroupResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'type',
        'is_variation',
        'required',
        'allow_multiple',
        'min_select',
        'max_select',
        'sort_order',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'options',
        'items',
    ];
}
