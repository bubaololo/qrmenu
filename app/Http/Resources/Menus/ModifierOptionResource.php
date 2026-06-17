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
        'linked_menu_item_id',
        'sort_order',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'childGroups',
    ];
}
