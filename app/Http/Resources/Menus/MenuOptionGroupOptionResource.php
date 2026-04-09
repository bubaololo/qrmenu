<?php

namespace App\Http\Resources\Menus;

use App\Models\MenuOptionGroupOption;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin MenuOptionGroupOption
 */
class MenuOptionGroupOptionResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'price_adjust',
        'is_default',
        'sort_order',
    ];
}
