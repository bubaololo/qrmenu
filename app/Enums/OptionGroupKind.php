<?php

namespace App\Enums;

enum OptionGroupKind: string
{
    /** Pick exactly one — defines how the dish is served (hot/cold, size). */
    case Variant = 'variant';

    /** Pick zero or more additive extras (extra shot, toppings). */
    case Addon = 'addon';
}
