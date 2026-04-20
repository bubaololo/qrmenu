<?php

namespace App\Policies;

use App\Models\Hall;
use App\Models\Restaurant;
use App\Models\User;

class HallPolicy
{
    public function create(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->owners()->where('user_id', $user->id)->exists();
    }

    public function view(User $user, Hall $hall): bool
    {
        return $hall->restaurant->users()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Hall $hall): bool
    {
        return $hall->restaurant->owners()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, Hall $hall): bool
    {
        return $this->update($user, $hall);
    }
}
