<?php

namespace App\Http\Resources\Orders;

use App\Models\Bill;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin Bill
 */
class BillResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'dining_table_id',
        'status',
        'total_amount',
        'currency',
        'opened_at',
        'closed_at',
        'closed_by_user_id',
        'created_at',
        'updated_at',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'diningTable',
        'orders',
        'closedBy',
    ];
}
