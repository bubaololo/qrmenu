<?php

namespace App\Http\Resources\Orders;

use App\Models\OrderItemModifier;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin OrderItemModifier
 */
class OrderItemModifierResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'modifier_group_id',
        'modifier_option_id',
        'group_name_snapshot',
        'option_name_snapshot',
        'pricing_mode_snapshot',
        'qty',
        'portion_numerator',
        'portion_denominator',
        'unit_price_snapshot',
        'line_amount_snapshot',
        'sort_order',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'children',
    ];
}
