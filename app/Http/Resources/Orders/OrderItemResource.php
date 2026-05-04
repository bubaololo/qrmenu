<?php

namespace App\Http\Resources\Orders;

use App\Models\OrderItem;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'menu_item_id',
        'variation_option_id',
        'quantity',
        'unit_price',
        'currency',
        'selected_options',
        'kitchen_status',
        'started_cooking_at',
        'ready_at',
        'served_at',
        'note',
        'created_at',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'menuItem',
        'variationOption',
    ];
}
