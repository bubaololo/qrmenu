<?php

namespace App\Enums;

enum PriceType: string
{
    case Fixed = 'fixed';
    case Range = 'range';
    case From = 'from';
    case Variable = 'variable';
}
