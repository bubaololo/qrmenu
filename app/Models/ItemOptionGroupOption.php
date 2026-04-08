<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\ItemOptionGroupOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemOptionGroupOption extends Model
{
    /** @use HasFactory<ItemOptionGroupOptionFactory> */
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'group_id',
        'price_adjust',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_adjust' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ItemOptionGroup::class, 'group_id');
    }
}
