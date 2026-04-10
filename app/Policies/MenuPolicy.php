<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\User;

class MenuPolicy
{
    /**
     * Owner or waiter can view menus.
     */
    public function view(User $user, Menu $menu): bool
    {
        return $menu->restaurant->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Only owner can create menus for a restaurant.
     */
    public function create(User $user, ?Restaurant $restaurant = null): bool
    {
        if ($restaurant === null) {
            return $user->restaurants()->wherePivot('role', 'owner')->exists();
        }

        return $restaurant->owners()->where('user_id', $user->id)->exists();
    }

    /**
     * Only owner can update a menu.
     */
    public function update(User $user, Menu $menu): bool
    {
        return $menu->restaurant->owners()->where('user_id', $user->id)->exists();
    }

    /**
     * Only owner can delete a menu.
     */
    public function delete(User $user, Menu $menu): bool
    {
        return $this->update($user, $menu);
    }
}
