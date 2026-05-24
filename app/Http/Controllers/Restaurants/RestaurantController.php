<?php

namespace App\Http\Controllers\Restaurants;

use App\Actions\GenerateQrCode;
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
use Illuminate\Http\Response;
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
        $restaurant = Restaurant::create([
            'created_by_user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return (new RestaurantResource($restaurant))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a restaurant.
     */
    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): RestaurantResource
    {
        Gate::authorize('update', $restaurant);

        $restaurant->update($request->validated());

        return new RestaurantResource($restaurant);
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
     * Return a PNG QR code that links to the restaurant's public menu page.
     *
     * The encoded URL is `{app_url}/{restaurant.id}`. A restaurant always has
     * at most one active menu, so the QR targets the restaurant, not a specific menu.
     */
    public function qr(Restaurant $restaurant, GenerateQrCode $generateQr): Response
    {
        Gate::authorize('view', $restaurant);

        $url = config('app.url').'/'.$restaurant->id;

        return $generateQr($url);
    }

    /**
     * Menus (one per restaurant) with sections and items for all owned restaurants.
     */
    public function activeMenus(Request $request): AnonymousResourceCollection
    {
        $restaurantIds = $request->user()
            ->ownedRestaurants()
            ->pluck('restaurants.id');

        $menus = Menu::query()
            ->whereIn('restaurant_id', $restaurantIds)
            ->with(['restaurant', 'sections'])
            ->get();

        return ActiveMenuResource::collection($menus);
    }
}
