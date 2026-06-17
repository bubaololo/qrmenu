<?php

namespace App\Enums;

/**
 * How a modifier group's option price composes into the line price.
 *
 *  - Replace: the chosen option price is the ABSOLUTE unit price (replaces the
 *    dish base). Used for a "Size" axis. A replace group is single-select.
 *  - Add: each chosen option price is a signed DELTA added on top. Used for
 *    "Extras". Multi-select.
 */
enum ModifierPricingMode: string
{
    case Replace = 'replace';
    case Add = 'add';
}
