<?php

namespace App\Actions;

use App\Data\ZoneData;
use App\Models\Restaurant;
use App\Models\Zone;

class StoreZoneAction
{
    public function __invoke(Restaurant $restaurant, ZoneData $data): Zone
    {
        return Zone::create([
            'restaurant_id' => $restaurant->id,
            'name' => $data->name,
            'color' => $data->color,
            'sort_order' => $data->sort_order,
            'is_active' => $data->is_active,
        ]);
    }
}
