<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function updateRestaurant(Request $request, int $restaurantId): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:4096']]);

        $restaurant = Restaurant::findOrFail($restaurantId);
        Gate::authorize('update', $restaurant);

        $this->deleteFile($restaurant->image);

        $path = $request->file('image')->store('restaurants', 'public');
        $restaurant->update(['image' => $path]);

        return response()->json([
            'data' => ['image_url' => Storage::disk('public')->url($path)],
        ]);
    }

    public function deleteRestaurant(Request $request, int $restaurantId): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($restaurantId);
        Gate::authorize('update', $restaurant);

        $this->deleteFile($restaurant->image);
        $restaurant->update(['image' => null]);

        return response()->json(null, 204);
    }

    public function updateMenuItem(Request $request, int $itemId): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:4096']]);

        $item = MenuItem::with('section.menu.restaurant')->findOrFail($itemId);
        Gate::authorize('update', $item->section->menu->restaurant);

        $this->deleteFile($item->image);

        $path = $request->file('image')->store('menu-items', 'public');
        $item->update(['image' => $path]);

        return response()->json([
            'data' => ['image_url' => Storage::disk('public')->url($path)],
        ]);
    }

    public function deleteMenuItem(Request $request, int $itemId): JsonResponse
    {
        $item = MenuItem::with('section.menu.restaurant')->findOrFail($itemId);
        Gate::authorize('update', $item->section->menu->restaurant);

        $this->deleteFile($item->image);
        $item->update(['image' => null]);

        return response()->json(null, 204);
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
