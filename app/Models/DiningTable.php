<?php

namespace App\Models;

use App\Enums\DiningTableShape;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    protected $fillable = [
        'hall_id',
        'number',
        'capacity',
        'shape',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shape' => DiningTableShape::class,
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'rotation' => 'float',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }
}
