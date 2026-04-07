<?php

namespace App\Models;

use App\Enums\RestaurantUserRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RestaurantUser extends Pivot
{
    protected $table = 'restaurant_users';

    /** @var bool The table has its own id column. */
    public $incrementing = true;

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => RestaurantUserRole::class,
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
