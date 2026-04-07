<?php

namespace App\Observers;

use App\Enums\RestaurantUserRole;
use App\Models\Restaurant;

class RestaurantObserver
{
    /**
     * Auto-add the creator as owner in restaurant_users when a restaurant is created.
     */
    public function created(Restaurant $restaurant): void
    {
        $userId = $restaurant->created_by_user_id;

        if ($userId) {
            $restaurant->restaurantUsers()->create([
                'user_id' => $userId,
                'role' => RestaurantUserRole::Owner,
            ]);
        }
    }
}
