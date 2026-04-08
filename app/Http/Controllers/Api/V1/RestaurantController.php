<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    /**
     * List owned restaurants.
     *
     * Returns all restaurants where the authenticated user is an owner.
     *
     * @operationId listRestaurants
     * @tags Restaurants
     *
     * @response 200 {
     *   "data": [
     *     { "id": 1, "name": "Phở Hà Nội", "city": "Hanoi", "country": "Vietnam", "currency": "VND" }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $restaurants = $request->user()
            ->ownedRestaurants()
            ->orderBy('restaurants.created_at', 'desc')
            ->get(['restaurants.id', 'restaurants.city', 'restaurants.country', 'restaurants.currency', 'restaurants.primary_language']);

        return response()->json([
            'data' => $restaurants->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->translate('name', $r->primary_language ?? 'und') ?? "Restaurant #{$r->id}",
                'city' => $r->city,
                'country' => $r->country,
                'currency' => $r->currency,
            ]),
        ]);
    }

    /**
     * Active menus.
     *
     * Returns the active menu (with sections and items) for each of the
     * authenticated user's owned restaurants. Restaurants without an
     * active menu are omitted.
     *
     * @operationId activeMenus
     * @tags Restaurants
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "restaurant_id": 1,
     *       "restaurant_name": "Phở Hà Nội",
     *       "menu_id": 7,
     *       "detected_date": "2026-04-07",
     *       "source_locale": "vi",
     *       "sections": [
     *         {
     *           "id": 12,
     *           "name": "Phở",
     *           "sort_order": 0,
     *           "items": [
     *             {
     *               "id": 55,
     *               "name": "Phở bò",
     *               "price_type": "fixed",
     *               "price_value": "79000.00",
     *               "price_original_text": "79.000",
     *               "starred": false
     *             }
     *           ]
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function activeMenus(Request $request): JsonResponse
    {
        $restaurantIds = $request->user()
            ->ownedRestaurants()
            ->pluck('restaurants.id');

        $menus = Menu::active()
            ->whereIn('restaurant_id', $restaurantIds)
            ->with([
                'restaurant',
                'sections' => fn ($q) => $q->orderBy('sort_order'),
                'sections.items' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->get();

        return response()->json([
            'data' => $menus->map(fn (Menu $menu) => [
                'restaurant_id' => $menu->restaurant_id,
                'restaurant_name' => $menu->restaurant?->translate('name', $menu->restaurant->primary_language ?? 'und') ?? "Restaurant #{$menu->restaurant_id}",
                'menu_id' => $menu->id,
                'source_locale' => $menu->source_locale,
                'detected_date' => $menu->detected_date?->toDateString(),
                'sections' => $menu->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'name' => $section->translate('name', $menu->source_locale ?? 'und'),
                    'sort_order' => $section->sort_order,
                    'items' => $section->items->map(fn ($item) => [
                        'id' => $item->id,
                        'name' => $item->translate('name', $menu->source_locale ?? 'und'),
                        'description' => $item->translate('description', $menu->source_locale ?? 'und'),
                        'starred' => $item->starred,
                        'price_type' => $item->price_type?->value,
                        'price_value' => $item->price_value,
                        'price_min' => $item->price_min,
                        'price_max' => $item->price_max,
                        'price_unit' => $item->price_unit,
                        'price_original_text' => $item->price_original_text,
                    ]),
                ]),
            ]),
        ]);
    }

    /**
     * Create a new empty restaurant owned by the authenticated user.
     *
     * @operationId createRestaurant
     * @tags Restaurants
     */
    public function store(Request $request): JsonResponse
    {
        $restaurant = Restaurant::create([
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $restaurant->id,
                'name' => "Restaurant #{$restaurant->id}",
            ],
        ], 201);
    }
}
