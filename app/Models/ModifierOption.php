<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\ModifierOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One choice within a {@see ModifierGroup}. `price` is interpreted by the
 * parent group's pricing_mode: ABSOLUTE when `replace` (null falls back to the
 * dish's price_value), a signed DELTA when `add` (null = 0).
 *
 * `linked_menu_item_id` lets an option be sourced from another menu item
 * (combos) — its name falls back to that item's name and the pricing/selection
 * walker pulls in that item's own groups (phase 3).
 */
class ModifierOption extends Model
{
    /** @use HasFactory<ModifierOptionFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var array<int, string> */
    protected $appends = ['name'];

    protected $fillable = [
        'group_id',
        'price',
        'is_default',
        'default_qty',
        'max_qty',
        'linked_menu_item_id',
        'sort_order',
    ];

    /** Pending translation value to be written after save */
    protected ?string $pendingName = null;

    protected static function booted(): void
    {
        static::saved(function (ModifierOption $option) {
            $locale = $option->group?->menu?->source_locale;
            if ($option->pendingName !== null && $locale !== null && $locale !== 'mixed') {
                $option->setTranslation('name', $locale, $option->pendingName, true);
                $option->pendingName = null;
            }
        });

        // Nested groups hanging off this option are removed by FK cascade
        // (no events fire), orphaning their translations. Clear them up-front.
        static::deleting(function (ModifierOption $option) {
            $childGroupIds = ModifierGroup::query()->where('parent_option_id', $option->id)->pluck('id');
            foreach ($childGroupIds as $groupId) {
                // Reuse the group's own subtree cleanup by instantiating it.
                $group = ModifierGroup::query()->find($groupId);
                $group?->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_default' => 'boolean',
            'default_qty' => 'integer',
            'max_qty' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getNameAttribute(): ?string
    {
        $own = $this->localizedText('name');
        if ($own !== null && $own !== '') {
            return $own;
        }

        return $this->relationLoaded('linkedItem') ? $this->linkedItem?->name : null;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->pendingName = $value;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'group_id');
    }

    public function linkedItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'linked_menu_item_id');
    }

    /** Groups revealed when this option is chosen (recursive nesting edge). */
    public function childGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class, 'parent_option_id')->orderBy('sort_order');
    }
}
