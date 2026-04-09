<?php

namespace App\Http\Resources\Menus;

use App\Models\MenuItem;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin MenuItem
 */
class MenuItemResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'description',
        'starred',
        'price_type',
        'price_value',
        'price_min',
        'price_max',
        'price_unit',
        'price_original_text',
        'image_url',
        'thumb_url',
        'sort_order',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'optionGroups',
    ];
}
