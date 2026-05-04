<?php

declare(strict_types=1);

/*
 | Whitelist of icon IDs the LLM may pick for menu_sections.icon.
 | IDs correspond 1:1 to filenames in resources/icons/menu/*.svg.
 | Source pack: Hugeicons Rounded / Stroke (Food Drinks subset).
 |
 | Curated for SEA (Vietnam, Thailand) + European cuisine commonly served
 | in those markets. Names are 1–4 words and semantically explicit so the
 | LLM can map a section heading to an icon with minimal ambiguity.
 */
return [
    'allowed' => [
        // asian mains
        'noodle-bowl', 'rice-bowl', 'dim-sum', 'sushi', 'mochi',

        // western mains
        'pizza', 'spaghetti', 'burger', 'hotdog', 'wrap', 'french-fries',

        // grill & meat
        'steak', 'chicken-leg', 'sausage', 'grill',

        // seafood
        'fish', 'prawn', 'crab', 'shellfish', 'octopus', 'snail',

        // soup, curry, hot pot
        'soup-pot',

        // bread & pastry
        'baguette', 'croissant', 'pie',

        // breakfast & dairy
        'eggs', 'cheese',

        // vegetarian / healthy
        'salad', 'healthy-food',

        // sweets
        'cupcake', 'cake', 'donut', 'cookie', 'ice-cream', 'chocolate',

        // hot drinks
        'iced-coffee', 'hot-coffee', 'tea',

        // cold drinks
        'bubble-tea', 'soft-drink', 'soda-can', 'cocktail',
        'milk', 'yogurt',

        // chef's specials
        'chef-hat',

        // add-ons / extras / toppings sections
        'extras',
    ],
];
