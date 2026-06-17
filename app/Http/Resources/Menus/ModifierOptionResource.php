<?php

namespace App\Http\Resources\Menus;

use App\Models\ModifierOption;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin ModifierOption
 */
class ModifierOptionResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'price',
        'is_default',
        'default_qty',
        'max_qty',
        'sort_order',
        // Size-dependent matrix: [{driver_option_id, price}] (empty when flat).
        'prices',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'childGroups',
    ];
}
