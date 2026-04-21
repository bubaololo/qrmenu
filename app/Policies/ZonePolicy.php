<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;
use App\Models\Zone;

class ZonePolicy
{
    public function create(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->owners()->where('user_id', $user->id)->exists();
    }

    public function view(User $user, Zone $zone): bool
    {
        return $zone->restaurant->users()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Zone $zone): bool
    {
        return $zone->restaurant->owners()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, Zone $zone): bool
    {
        return $this->update($user, $zone);
    }
}
