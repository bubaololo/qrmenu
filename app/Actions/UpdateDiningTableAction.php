<?php

namespace App\Actions;

use App\Data\DiningTableData;
use App\Models\DiningTable;
use App\Models\TableShape;

class UpdateDiningTableAction
{
    public function __invoke(DiningTable $table, DiningTableData $data): DiningTable
    {
        $table->update([
            'table_shape_id' => TableShape::idFor($data->shape),
            'number' => $data->number,
            'capacity' => $data->capacity,
            'x' => $data->x,
            'y' => $data->y,
            'width' => $data->width,
            'height' => $data->height,
            'rotation' => $data->rotation,
            'sort_order' => $data->sort_order,
            'is_active' => $data->is_active,
        ]);

        return $table->fresh()->load('tableShape');
    }
}
