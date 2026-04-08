<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    /**
     * Any authenticated user can create a restaurant.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Owner or waiter can view the restaurant.
     */
    public function view(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Only owner can update.
     */
    public function update(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->owners()->where('user_id', $user->id)->exists();
    }

    /**
     * Only owner can delete.
     */
    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $this->update($user, $restaurant);
    }
}
