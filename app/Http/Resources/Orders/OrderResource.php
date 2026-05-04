<?php

namespace App\Http\Resources\Orders;

use App\Models\Order;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'bill_id',
        'guest_token',
        'status',
        'note',
        'placed_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancelled_reason',
        'created_at',
        'updated_at',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'bill',
        'items',
    ];
}
