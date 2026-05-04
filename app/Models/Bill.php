<?php

namespace App\Models;

use App\Enums\BillStatus;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    protected $fillable = [
        'dining_table_id',
        'status',
        'total_amount',
        'currency',
        'opened_at',
        'closed_at',
        'closed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => BillStatus::class,
            'total_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', BillStatus::Open->value);
    }

    /**
     * Resolve the owning restaurant via dining_table → zone.
     * Requires `diningTable.zone` to be eager-loaded; falls back to a single query otherwise.
     */
    public function getRestaurantIdAttribute(): ?int
    {
        return $this->diningTable?->zone?->restaurant_id;
    }

    /**
     * Recompute total from associated orders' items. Does not persist.
     */
    public function recalculateTotal(): float
    {
        $sum = 0.0;
        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $sum += (float) $item->unit_price * (int) $item->quantity;
            }
        }

        return round($sum, 2);
    }
}
