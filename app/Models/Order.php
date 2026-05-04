<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'guest_token',
        'status',
        'note',
        'placed_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'placed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OrderStatus::Pending->value,
            OrderStatus::InProgress->value,
        ]);
    }

    /** @param  Builder<Order>  $query */
    public function scopeForRestaurant(Builder $query, int $restaurantId): Builder
    {
        return $query->whereHas(
            'bill.diningTable.zone',
            fn (Builder $q) => $q->where('restaurant_id', $restaurantId),
        );
    }

    public function totalAmount(): float
    {
        $sum = 0.0;
        foreach ($this->items as $item) {
            $sum += (float) $item->unit_price * (int) $item->quantity;
        }

        return round($sum, 2);
    }
}
