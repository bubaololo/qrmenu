<?php

namespace App\Actions;

use App\Data\HallData;
use App\Models\Hall;

class UpdateHallAction
{
    public function __invoke(Hall $hall, HallData $data): Hall
    {
        $hall->update([
            'color' => $data->color,
            'sort_order' => $data->sort_order,
            'is_active' => $data->is_active,
        ]);

        $hall->setTranslation('name', 'und', $data->name, isInitial: true);

        return $hall->fresh();
    }
}
