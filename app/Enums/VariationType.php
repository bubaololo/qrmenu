<?php

namespace App\Enums;

enum VariationType: string
{
    case Portion = 'portion';
    case Size = 'size';
    case SpiceLevel = 'spice_level';
    case Sauce = 'sauce';
    case Base = 'base';
    case Flavor = 'flavor';
    case Unit = 'unit';
}
