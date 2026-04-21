<?php

namespace App\Actions;

use App\Data\ZoneData;
use App\Models\Restaurant;
use App\Models\Zone;

class StoreZoneAction
{
    public function __invoke(Restaurant $restaurant, ZoneData $data): Zone
    {
        $zone = Zone::create([
            'restaurant_id' => $restaurant->id,
            'color' => $data->color,
            'sort_order' => $data->sort_order,
            'is_active' => $data->is_active,
        ]);

        $zone->setTranslation('name', 'und', $data->name, isInitial: true);

        return $zone->fresh();
    }
}
