<?php

namespace App\Policies;

use App\Models\DiningTable;
use App\Models\User;
use App\Models\Zone;

class DiningTablePolicy
{
    public function create(User $user, ?Zone $zone = null): bool
    {
        if ($zone !== null) {
            return $zone->restaurant->owners()->where('user_id', $user->id)->exists();
        }

        return $user->ownedRestaurants()->exists();
    }

    public function view(User $user, DiningTable $diningTable): bool
    {
        return $diningTable->zone->restaurant->users()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, DiningTable $diningTable): bool
    {
        return $diningTable->zone->restaurant->owners()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, DiningTable $diningTable): bool
    {
        return $this->update($user, $diningTable);
    }
}
