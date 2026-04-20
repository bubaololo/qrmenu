<?php

namespace App\Data;

use App\Enums\DiningTableShape;

readonly class DiningTableData
{
    public function __construct(
        public int $number,
        public int $capacity,
        public DiningTableShape $shape,
        public ?float $x,
        public ?float $y,
        public ?float $width,
        public ?float $height,
        public float $rotation,
        public int $sort_order,
        public bool $is_active,
    ) {}
}
