<?php

namespace App\Http\Resources\Menus;

use App\Models\MenuSection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin MenuSection
 */
class MenuSectionResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'sort_order',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'items',
        'optionGroups',
    ];
}
