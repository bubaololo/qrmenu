<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user, ?Restaurant $restaurant = null): bool
    {
        if ($restaurant === null) {
            // Filament / global context: allow admins or any user attached to at
            // least one restaurant (role-agnostic — same rule as `view`).
            return $user->isAdmin() || $user->restaurants()->exists();
        }

        return $restaurant->users()->where('user_id', $user->id)->exists();
    }

    public function view(User $user, Order $order): bool
    {
        $restaurantId = $this->resolveRestaurantId($order);
        if ($restaurantId === null) {
            return false;
        }

        return Restaurant::query()
            ->whereKey($restaurantId)
            ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    public function update(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }

    private function resolveRestaurantId(Order $order): ?int
    {
        $order->loadMissing('bill.diningTable.zone');

        return $order->bill?->diningTable?->zone?->restaurant_id;
    }
}
