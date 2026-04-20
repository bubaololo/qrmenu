<?php

namespace App\Actions;

use App\Data\HallData;
use App\Models\Hall;
use App\Models\Restaurant;

class StoreHallAction
{
    public function __invoke(Restaurant $restaurant, HallData $data): Hall
    {
        $hall = Hall::create([
            'restaurant_id' => $restaurant->id,
            'color' => $data->color,
            'sort_order' => $data->sort_order,
            'is_active' => $data->is_active,
        ]);

        $hall->setTranslation('name', 'und', $data->name, isInitial: true);

        return $hall->fresh();
    }
}
