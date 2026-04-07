<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RestaurantUserRole;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    /**
     * Upload restaurant image.
     *
     * @operationId uploadRestaurantImage
     *
     * @tags Restaurants
     *
     * @response 200 {"data": {"image_url": "http://..."}}
     * @response 403 {"message": "Forbidden."}
     */
    public function updateRestaurant(Request $request, int $restaurantId): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $restaurant = Restaurant::findOrFail($restaurantId);

        $this->authorizeOwner($request, $restaurantId);

        $this->deleteFile($restaurant->image);

        $path = $request->file('image')->store('restaurants', 'public');
        $restaurant->update(['image' => $path]);

        return response()->json([
            'data' => ['image_url' => Storage::disk('public')->url($path)],
        ]);
    }

    /**
     * Delete restaurant image.
     *
     * @operationId deleteRestaurantImage
     *
     * @tags Restaurants
     *
     * @response 204 description="Image deleted"
     * @response 403 {"message": "Forbidden."}
     */
    public function deleteRestaurant(Request $request, int $restaurantId): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($restaurantId);

        $this->authorizeOwner($request, $restaurantId);

        $this->deleteFile($restaurant->image);
        $restaurant->update(['image' => null]);

        return response()->json(null, 204);
    }

    /**
     * Upload menu item image.
     *
     * @operationId uploadMenuItemImage
     *
     * @tags Menus
     *
     * @response 200 {"data": {"image_url": "http://..."}}
     * @response 403 {"message": "Forbidden."}
     */
    public function updateMenuItem(Request $request, int $itemId): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $item = MenuItem::with('section.menu')->findOrFail($itemId);

        $this->authorizeOwner($request, $item->section->menu->restaurant_id);

        $this->deleteFile($item->image);

        $path = $request->file('image')->store('menu-items', 'public');
        $item->update(['image' => $path]);

        return response()->json([
            'data' => ['image_url' => Storage::disk('public')->url($path)],
        ]);
    }

    /**
     * Delete menu item image.
     *
     * @operationId deleteMenuItemImage
     *
     * @tags Menus
     *
     * @response 204 description="Image deleted"
     * @response 403 {"message": "Forbidden."}
     */
    public function deleteMenuItem(Request $request, int $itemId): JsonResponse
    {
        $item = MenuItem::with('section.menu')->findOrFail($itemId);

        $this->authorizeOwner($request, $item->section->menu->restaurant_id);

        $this->deleteFile($item->image);
        $item->update(['image' => null]);

        return response()->json(null, 204);
    }

    private function authorizeOwner(Request $request, int $restaurantId): void
    {
        $isOwner = RestaurantUser::where('restaurant_id', $restaurantId)
            ->where('user_id', $request->user()->id)
            ->where('role', RestaurantUserRole::Owner->value)
            ->exists();

        abort_if(! $isOwner, 403);
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
