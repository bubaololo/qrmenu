<?php

namespace App\Http\Resources\Menus;

use App\Models\ModifierGroup;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin ModifierGroup
 */
class ModifierGroupResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'pricing_mode',
        'selection_type',
        'selection_min',
        'selection_max',
        'required',
        'charge_above',
        'portion_denominator',
        'sort_order',
        // Size-dependent pricing: the single-select driver group whose chosen
        // option drives this group's option prices (null = flat pricing).
        'price_driver_group_id',
        // Usage count — populated by `->withCount('items')` on the index
        // endpoint; null on responses that don't load it.
        'items_count',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'options',
        'items',
    ];
}
