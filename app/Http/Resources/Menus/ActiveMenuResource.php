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
            'sections_count' => $this->sections->count(),
        ];
    }
}
