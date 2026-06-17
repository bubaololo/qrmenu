<?php

namespace App\Models;

use App\Enums\OrderItemKitchenStatus;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'menu_item_name_snapshot',
        'base_price_snapshot',
        'quantity',
        'unit_price',
        'currency',
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
            'base_price_snapshot' => 'decimal:2',
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

    /** Top-level chosen modifiers (nested children hang off each via parent_id). */
    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class, 'order_item_id')
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }

    public function lineTotal(): float
    {
        return round((float) $this->unit_price * (int) $this->quantity, 2);
    }
}
