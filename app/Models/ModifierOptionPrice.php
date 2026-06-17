<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Price of an add-on {@see ModifierOption} when a specific driver option
 * (an option of the group's `price_driver_group_id`) is chosen — the
 * size-dependent pricing matrix.
 */
class ModifierOptionPrice extends Model
{
    protected $fillable = [
        'option_id',
        'driver_option_id',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'option_id');
    }

    public function driverOption(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'driver_option_id');
    }
}
