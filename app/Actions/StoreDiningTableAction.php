<?php

namespace App\Actions;

use App\Data\DiningTableData;
use App\Models\DiningTable;
use App\Models\Hall;

class StoreDiningTableAction
{
    public function __invoke(Hall $hall, DiningTableData $data): DiningTable
    {
        return DiningTable::create([
            'hall_id' => $hall->id,
            'number' => $data->number,
            'capacity' => $data->capacity,
            'shape' => $data->shape,
            'x' => $data->x,
            'y' => $data->y,
            'width' => $data->width,
            'height' => $data->height,
            'rotation' => $data->rotation,
            'sort_order' => $data->sort_order,
            'is_active' => $data->is_active,
        ]);
    }
}
