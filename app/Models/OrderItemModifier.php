<?php

namespace App\Models;

use App\Enums\ModifierPricingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A node in an order line's recursive modifier-selection snapshot. The tree
 * mirrors the catalog tree (top-level rows have parent_id = null; nested
 * modifiers point at their parent option row). The *_snapshot columns freeze
 * names/prices so history survives a later option rename/delete.
 */
class OrderItemModifier extends Model
{
    protected $fillable = [
        'order_item_id',
        'parent_id',
        'modifier_group_id',
        'modifier_option_id',
        'group_name_snapshot',
        'option_name_snapshot',
        'pricing_mode_snapshot',
        'qty',
        'portion_numerator',
        'portion_denominator',
        'unit_price_snapshot',
        'line_amount_snapshot',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pricing_mode_snapshot' => ModifierPricingMode::class,
            'qty' => 'integer',
            'portion_numerator' => 'integer',
            'portion_denominator' => 'integer',
            'unit_price_snapshot' => 'decimal:2',
            'line_amount_snapshot' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrderItemModifier::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class, 'parent_id')->orderBy('sort_order');
    }
}
