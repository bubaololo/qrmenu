<?php

namespace App\Actions;

use App\Models\DiningTable;
use App\Models\Restaurant;

class BuildPublicMenuUrl
{
    /**
     * Public menu URL for a restaurant: `{app_url}/{restaurant.id}`.
     */
    public function forRestaurant(Restaurant $restaurant): string
    {
        return config('app.url').'/'.$restaurant->id;
    }

    /**
     * Public menu URL scoped to a table: `{app_url}/{restaurant.uniqid}/t/{table.uniqid}`.
     */
    public function forTable(DiningTable $table): string
    {
        $restaurant = $table->zone->restaurant;

        return config('app.url').'/'.$restaurant->uniqid.'/t/'.$table->uniqid;
    }
}
