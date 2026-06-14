<?php

namespace App\Http\Resources\Restaurants;

use App\Actions\BuildPublicMenuUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class RestaurantResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'uniqid',
        'name',
        'address',
        'city',
        'country',
        'phone',
        'currency',
        'primary_language',
        'opening_hours',
        'image_url',
        'thumb_url',
        'logo_url',
        'logo_thumb_url',
        'google_maps_url',
        'coordinates',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'menu',
    ];

    /**
     * @return array<int|string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            ...$this->attributes,
            'menu_url' => app(BuildPublicMenuUrl::class)->forRestaurant($this->resource),
        ];
    }
}
