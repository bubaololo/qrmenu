<?php

namespace App\Http\Controllers\Restaurants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurants\StoreRestaurantRequest;
use App\Http\Requests\Restaurants\UpdateRestaurantRequest;
use App\Http\Resources\Menus\ActiveMenuResource;
use App\Http\Resources\Restaurants\RestaurantResource;
use App\Models\Menu;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RestaurantController extends Controller
{
    /**
     * List all restaurants owned by the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
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
    public function store(StoreRestaurantRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $name = $validated['name'] ?? null;
        $address = $validated['address'] ?? null;
        unset($validated['name'], $validated['address']);

        $restaurant = Restaurant::create([
            'created_by_user_id' => $request->user()->id,
            ...$validated,
        ]);

        $locale = $restaurant->primary_language ?? 'und';

        if ($name !== null) {
            $restaurant->setTranslation('name', $locale, $name, isInitial: true);
        }

        if ($address !== null) {
            $restaurant->setTranslation('address', $locale, $address, isInitial: true);
        }

        return (new RestaurantResource($restaurant->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a restaurant.
     */
    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): RestaurantResource
    {
        Gate::authorize('update', $restaurant);

        $validated = $request->validated();

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
     * Delete a restaurant. Only the owner can delete.
     */
    public function destroy(Restaurant $restaurant): JsonResponse
    {
        Gate::authorize('delete', $restaurant);

        $restaurant->delete();

        return response()->json(null, 204);
    }

    /**
     * Active menus with sections and items for all owned restaurants.
     */
    public function activeMenus(Request $request): AnonymousResourceCollection
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

        return ActiveMenuResource::collection($menus);
    }
}
