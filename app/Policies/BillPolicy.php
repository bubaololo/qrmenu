<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\Restaurant;
use App\Models\User;

class BillPolicy
{
    public function viewAny(User $user, ?Restaurant $restaurant = null): bool
    {
        if ($restaurant === null) {
            return $user->isAdmin() || $user->restaurants()->exists();
        }

        return $restaurant->users()->where('user_id', $user->id)->exists();
    }

    public function view(User $user, Bill $bill): bool
    {
        $bill->loadMissing('diningTable.zone');
        $restaurantId = $bill->diningTable?->zone?->restaurant_id;

        if ($restaurantId === null) {
            return false;
        }

        return Restaurant::query()
            ->whereKey($restaurantId)
            ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    public function update(User $user, Bill $bill): bool
    {
        return $this->view($user, $bill);
    }
}
