<?php

namespace App\Enums;

enum RestaurantUserRole: string
{
    case Owner = 'owner';
    case Waiter = 'waiter';
}
