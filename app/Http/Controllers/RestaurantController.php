<?php

namespace App\Http\Controllers;

use App\Http\Resources\RestaurantResource;
use App\Models\Menu;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Gate;

class RestaurantController extends Controller
{
    /**
     * List owned restaurants.
     */
    public function index(Request $request): ResourceCollection
    {
        $restaurants = $request->user()
            ->ownedRestaurants()
            ->orderBy('restaurants.created_at', 'desc')
            ->get();

        return RestaurantResource::collection($restaurants);
    }

    /**
     * Show a single restaurant.
     */
    public function show(Restaurant $restaurant): RestaurantResource
    {
        Gate::authorize('view', $restaurant);

        return new RestaurantResource($restaurant);
    }

    /**
     * Create a new restaurant owned by the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        // Observer auto-attaches creator as owner in restaurant_users
        $restaurant = Restaurant::create([
            'created_by_user_id' => $request->user()->id,
        ]);

        return (new RestaurantResource($restaurant))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a restaurant.
     */
    public function update(Request $request, Restaurant $restaurant): RestaurantResource
    {
        Gate::authorize('update', $restaurant);

        $validated = $request->validate([
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'primary_language' => ['sometimes', 'nullable', 'string', 'max:10'],
            'opening_hours' => ['sometimes', 'nullable', 'array'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $name = $validated['name'] ?? null;
        $address = $validated['address'] ?? null;
        unset($validated['name'], $validated['address']);

        $restaurant->update($validated);

        $locale = $restaurant->primary_language ?? 'und';

        if ($name !== null) {
            $restaurant->setTranslation('name', $locale, $name, isInitial: true);
        }

        if ($address !== null) {
            $restaurant->setTranslation('address', $locale, $address, isInitial: true);
        }

        return new RestaurantResource($restaurant->fresh());
    }

    /**
     * Active menus with sections and items.
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
}
