<?php

namespace App\Http\Resources\Menus;

use App\Actions\BuildPublicMenuUrl;
use App\Models\Menu;
use App\Models\ModifierGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Menu
 */
class FullMenuResource extends JsonResource
{
    /** @param  array<int|string, array{text?: float, bbox?: float}>  $confidenceMap */
    public function __construct(mixed $resource, private array $confidenceMap = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        // Final fallback is 'en' rather than a pseudo-locale — translate()
        // falls back to is_initial=true when this locale has no row, so 'en'
        // here just means "no explicit preference, give me the source text".
        $locale = $request->attributes->get('locale_from_header')
            ?? $this->source_locale
            ?? $this->restaurant?->primary_language
            ?? 'en';

        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'restaurant_menu_url' => $this->restaurant
                ? app(BuildPublicMenuUrl::class)->forRestaurant($this->restaurant)
                : null,
            'source_locale' => $this->source_locale,
            'locale' => $locale,
            'detected_date' => $this->detected_date?->toDateString(),
            'locales' => $this->availableLocales(),
            'sections' => $this->sections->map(fn ($section) => [
                'id' => $section->id,
                'name' => $section->translate('name', $locale),
                'icon_name' => $section->icon?->name,
                'is_active' => $section->is_active,
                'sort_order' => $section->sort_order,
                'items' => $section->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->translate('name', $locale),
                    'description' => $item->translate('description', $locale),
                    'starred' => $item->starred,
                    'is_visible' => $item->is_visible,
                    'is_orderable' => $item->is_orderable,
                    'price_type' => $item->price_type?->value,
                    'price_value' => $item->price_value,
                    'price_min' => $item->price_min,
                    'price_max' => $item->price_max,
                    'price_unit' => $item->price_unit,
                    'price_original_text' => $item->price_original_text,
                    'image_url' => $item->image_url,
                    'thumb_url' => $item->thumb_url,
                    'sort_order' => $item->sort_order,
                    'confidence' => $this->confidenceMap[$item->id] ?? null,
                    // Modifier groups: a 'replace' group's option price is the
                    // ABSOLUTE base; an 'add' group's option price is a DELTA.
                    'modifier_groups' => $item->modifierGroups
                        ->reject(fn ($group) => (bool) ($group->pivot->is_hidden ?? false))
                        ->map(fn ($group) => $this->serializeGroup($group, $locale))
                        ->values(),
                ]),
            ]),
        ];
    }

    /**
     * Serialize a modifier group with per-item effective selection rules and
     * its (recursive) options/child-groups tree.
     *
     * @return array<string, mixed>
     */
    private function serializeGroup(ModifierGroup $group, string $locale): array
    {
        return [
            'id' => $group->id,
            'name' => $group->translate('name', $locale),
            'pricing_mode' => $group->pricing_mode->value,
            'selection_type' => $group->selection_type,
            'selection_min' => (int) ($group->pivot?->selection_min_override ?? $group->selection_min),
            'selection_max' => $group->pivot?->selection_max_override ?? $group->selection_max,
            'required' => (bool) ($group->pivot?->required_override ?? $group->required),
            'charge_above' => $group->charge_above,
            'portion_denominator' => $group->portion_denominator,
            'sort_order' => $group->pivot?->sort_order ?? $group->sort_order,
            // Raw per-item overrides (null = inherit) for the admin editor; the
            // guest uses the effective values above. Null for nested groups.
            'overrides' => [
                'selection_min' => $group->pivot?->selection_min_override,
                'selection_max' => $group->pivot?->selection_max_override,
                'required' => isset($group->pivot->required_override) ? (bool) $group->pivot->required_override : null,
                'is_hidden' => (bool) ($group->pivot?->is_hidden ?? false),
                'sort_order' => $group->pivot?->sort_order,
            ],
            'options' => $group->options->map(fn ($opt) => [
                'id' => $opt->id,
                'name' => $opt->translate('name', $locale),
                'price' => $opt->price,
                'is_default' => $opt->is_default,
                'default_qty' => $opt->default_qty,
                'max_qty' => $opt->max_qty,
                'linked_menu_item_id' => $opt->linked_menu_item_id,
                'sort_order' => $opt->sort_order,
                'child_groups' => $opt->relationLoaded('childGroups')
                    ? $opt->childGroups->map(fn ($child) => $this->serializeGroup($child, $locale))->values()
                    : [],
            ])->values(),
        ];
    }
}
