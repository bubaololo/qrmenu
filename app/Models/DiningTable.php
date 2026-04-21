<?php

namespace App\Models;

use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'table_shape_id',
        'number',
        'capacity',
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
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'rotation' => 'float',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getShapeAttribute(): string
    {
        return $this->tableShape->name;
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function tableShape(): BelongsTo
    {
        return $this->belongsTo(TableShape::class);
    }
}
