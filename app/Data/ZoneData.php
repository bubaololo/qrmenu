<?php

namespace App\Data;

readonly class ZoneData
{
    public function __construct(
        public string $name,
        public string $color,
        public int $sort_order,
        public bool $is_active,
    ) {}
}
