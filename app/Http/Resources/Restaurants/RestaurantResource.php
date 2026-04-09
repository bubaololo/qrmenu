<?php

namespace App\Http\Resources\Restaurants;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class RestaurantResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'name',
        'address',
        'city',
        'country',
        'phone',
        'currency',
        'primary_language',
        'opening_hours',
        'image',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'activeMenu',
        'menus',
    ];
}
