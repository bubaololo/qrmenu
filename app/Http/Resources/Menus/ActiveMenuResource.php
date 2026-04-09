<?php

namespace App\Http\Resources\Menus;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Menu
 */
class ActiveMenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'restaurant_id' => $this->restaurant_id,
            'restaurant_name' => $this->restaurant?->translate(
                'name',
                $this->restaurant->primary_language ?? 'und'
            ) ?? "Restaurant #{$this->restaurant_id}",
            'menu_id' => $this->id,
            'source_locale' => $this->source_locale,
            'detected_date' => $this->detected_date?->toDateString(),
            'sections' => $this->sections->map(fn ($section) => [
                'id' => $section->id,
                'name' => $section->translate('name', $this->source_locale ?? 'und'),
                'sort_order' => $section->sort_order,
                'items' => $section->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->translate('name', $this->source_locale ?? 'und'),
                    'description' => $item->translate('description', $this->source_locale ?? 'und'),
                    'starred' => $item->starred,
                    'price_type' => $item->price_type?->value,
                    'price_value' => $item->price_value,
                    'price_min' => $item->price_min,
                    'price_max' => $item->price_max,
                    'price_unit' => $item->price_unit,
                    'price_original_text' => $item->price_original_text,
                ]),
            ]),
        ];
    }
}
