<?php

namespace App\Models;

use App\Enums\ModifierPricingMode;
use App\Models\Concerns\HasTranslations;
use Database\Factories\ModifierGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable group of choices attached to menu items. Carries the selection
 * rules (min/max/required, single/multi) and pricing mode (replace vs add).
 *
 *  - "Size"   => pricing_mode=replace, single, min=max=1, required.
 *  - "Extras" => pricing_mode=add, multi, min=0, max=null.
 *
 * Nesting: a group with a non-null parent_option_id is revealed only when that
 * option is chosen (otherwise it is a top-level group attached to items via the
 * menu_item_modifier_group junction).
 */
class ModifierGroup extends Model
{
    /** @use HasFactory<ModifierGroupFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var array<int, string> */
    protected $appends = ['name'];

    protected $fillable = [
        'menu_id',
        'parent_option_id',
        'pricing_mode',
        'selection_type',
        'selection_min',
        'selection_max',
        'required',
        'charge_above',
        'portion_denominator',
        'sort_order',
    ];

    /** Pending translation value to be written after save */
    protected ?string $pendingName = null;

    protected static function booted(): void
    {
        static::saved(function (ModifierGroup $group) {
            $locale = $group->menu?->source_locale;
            if ($group->pendingName !== null && $locale !== null && $locale !== 'mixed') {
                $group->setTranslation('name', $locale, $group->pendingName, true);
                $group->pendingName = null;
            }
        });

        // Descendant options and nested groups are removed by FK cascade (no
        // Eloquent events fire for them), so their polymorphic translations
        // would orphan. Clear the whole subtree up-front on direct delete.
        static::deleting(function (ModifierGroup $group) {
            $subtree = $group->collectDescendantTranslatableIds();
            if ($subtree['options'] !== []) {
                Translation::query()
                    ->where('translatable_type', ModifierOption::class)
                    ->whereIn('translatable_id', $subtree['options'])
                    ->delete();
            }
            if ($subtree['groups'] !== []) {
                Translation::query()
                    ->where('translatable_type', ModifierGroup::class)
                    ->whereIn('translatable_id', $subtree['groups'])
                    ->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'pricing_mode' => ModifierPricingMode::class,
            'selection_min' => 'integer',
            'selection_max' => 'integer',
            'required' => 'boolean',
            'charge_above' => 'integer',
            'portion_denominator' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getNameAttribute(): ?string
    {
        return $this->localizedText('name');
    }

    public function setNameAttribute(?string $value): void
    {
        $this->pendingName = $value;
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class, 'group_id')->orderBy('sort_order');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_modifier_group', 'group_id', 'item_id')
            ->withPivot(['selection_min_override', 'selection_max_override', 'required_override', 'is_hidden', 'sort_order']);
    }

    /** The option that reveals this group when nested (null = top-level). */
    public function parentOption(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'parent_option_id');
    }

    /**
     * Walk the nesting subtree and collect every descendant option id and
     * nested group id (excluding this group's own id, which HasTranslations
     * cleans up). Bounded by the validator-enforced nesting depth.
     *
     * @return array{groups: list<int>, options: list<int>}
     */
    public function collectDescendantTranslatableIds(): array
    {
        $allGroupIds = [];
        $allOptionIds = [];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $optionIds = ModifierOption::query()->whereIn('group_id', $frontier)->pluck('id')->all();
            $allOptionIds = array_merge($allOptionIds, $optionIds);

            $childGroupIds = $optionIds === []
                ? []
                : static::query()->whereIn('parent_option_id', $optionIds)->pluck('id')->all();
            $allGroupIds = array_merge($allGroupIds, $childGroupIds);
            $frontier = $childGroupIds;
        }

        return ['groups' => $allGroupIds, 'options' => $allOptionIds];
    }
}
