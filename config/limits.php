<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User-editable text field length limits
    |--------------------------------------------------------------------------
    |
    | Single source of truth for the max length (in characters) of admin-edited
    | text fields. Referenced from FormRequest rules so backend validation stays
    | consistent. The frontend mirrors these in `src/lib/field-limits.ts` — keep
    | the two in sync.
    |
    */

    // Menu entity names: items, sections, option groups, options, variations.
    'name' => 100,

    // Menu item description.
    'description' => 500,

    // Restaurant profile fields.
    'restaurant_name' => 100,
    'address' => 255,
    'phone' => 32,

    // Zone (hall area) name.
    'zone_name' => 60,

];
