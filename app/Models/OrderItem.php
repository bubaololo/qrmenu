<?php

namespace App\Models;

use App\Enums\OrderItemKitchenStatus;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
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
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'selected_options' => 'array',
            'kitchen_status' => OrderItemKitchenStatus::class,
            'started_cooking_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variationOption(): BelongsTo
    {
        return $this->belongsTo(MenuOptionGroupOption::class, 'variation_option_id');
    }

    public function lineTotal(): float
    {
        return round((float) $this->unit_price * (int) $this->quantity, 2);
    }
}
