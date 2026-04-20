<?php

namespace App\Enums;

enum DiningTableShape: string
{
    case Round = 'round';
    case Square = 'square';
    case Rectangular = 'rectangular';
    case BarCounter = 'bar_counter';
}
