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
    public function toArray(Request $request): array
    {
        $locale = $request->attributes->get('locale_from_header') ?? ($this->source_locale ?? 'und');

        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'source_locale' => $this->source_locale,
            'locale' => $locale,
            'detected_date' => $this->detected_date?->toDateString(),
            'is_active' => $this->is_active,
            'locales' => $this->availableLocales(),
            'sections' => $this->sections->map(fn ($section) => [
                'id' => $section->id,
                'name' => $section->translate('name', $locale),
                'sort_order' => $section->sort_order,
                'items' => $section->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->translate('name', $locale),
                    'description' => $item->translate('description', $locale),
                    'starred' => $item->starred,
                    'price_type' => $item->price_type?->value,
                    'price_value' => $item->price_value,
                    'price_min' => $item->price_min,
                    'price_max' => $item->price_max,
                    'price_unit' => $item->price_unit,
                    'price_original_text' => $item->price_original_text,
                    'sort_order' => $item->sort_order,
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
