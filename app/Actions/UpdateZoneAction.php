<?php

namespace App\Actions;

use App\Data\ZoneData;
use App\Models\Zone;

class UpdateZoneAction
{
    public function __invoke(Zone $zone, ZoneData $data): Zone
    {
        $zone->update([
            'name' => $data->name,
            'color' => $data->color,
            'sort_order' => $data->sort_order,
            'is_active' => $data->is_active,
        ]);

        return $zone;
    }
}
