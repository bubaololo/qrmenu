<?php

namespace App\Http\Resources\Menus;

use App\Models\Menu;
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
                    'option_groups' => $item->optionGroups->map(fn ($group) => [
                        'id' => $group->id,
                        'name' => $group->translate('name', $locale),
                        'is_variation' => $group->is_variation,
                        'required' => $group->required,
                        'allow_multiple' => $group->allow_multiple,
                        'min_select' => $group->min_select,
                        'max_select' => $group->max_select,
                        'sort_order' => $group->sort_order,
                        'options' => $group->options->map(fn ($opt) => [
                            'id' => $opt->id,
                            'name' => $opt->translate('name', $locale),
                            'price_adjust' => $opt->price_adjust,
                            'is_default' => $opt->is_default,
                            'sort_order' => $opt->sort_order,
                        ]),
                    ]),
                ]),
            ]),
        ];
    }
}
