<?php

namespace App\Policies;

use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\User;

class DiningTablePolicy
{
    public function create(User $user, Hall $hall): bool
    {
        return $hall->restaurant->owners()->where('user_id', $user->id)->exists();
    }

    public function view(User $user, DiningTable $diningTable): bool
    {
        return $diningTable->hall->restaurant->users()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, DiningTable $diningTable): bool
    {
        return $diningTable->hall->restaurant->owners()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, DiningTable $diningTable): bool
    {
        return $this->update($user, $diningTable);
    }
}
