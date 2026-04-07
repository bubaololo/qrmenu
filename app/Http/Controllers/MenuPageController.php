<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\View\View;

class MenuPageController extends Controller
{
    public function show(int $restaurant_id): View
    {
        $restaurant = Restaurant::with([
            'activeMenu.sections.items.variations.options',
            'activeMenu.sections.items.optionGroups.options',
        ])->findOrFail($restaurant_id);

        $menu = $restaurant->activeMenu;

        return view('menu', compact('restaurant', 'menu'));
    }
}
