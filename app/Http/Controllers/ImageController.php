<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImageJob;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\ImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function updateRestaurant(Request $request, int $restaurantId): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:10240']]);

        $restaurant = Restaurant::findOrFail($restaurantId);
        Gate::authorize('update', $restaurant);

        $tempPath = $request->file('image')->store('originals', config('image.disk'));

        ProcessImageJob::dispatch(
            Restaurant::class,
            $restaurant->id,
            $tempPath,
            'restaurants',
            $restaurant->image,
        );

        return response()->json(null, 202);
    }

    public function deleteRestaurant(Request $request, int $restaurantId): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($restaurantId);
        Gate::authorize('update', $restaurant);

        $this->deleteImageFiles($restaurant->image);
        $restaurant->update(['image' => null]);

        return response()->json(null, 204);
    }

    public function updateMenuItem(Request $request, int $itemId): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:10240']]);

        $item = MenuItem::with('section.menu.restaurant')->findOrFail($itemId);
        Gate::authorize('update', $item->section->menu->restaurant);

        $tempPath = $request->file('image')->store('originals', config('image.disk'));

        ProcessImageJob::dispatch(
            MenuItem::class,
            $item->id,
            $tempPath,
            'menu-items',
            $item->image,
        );

        return response()->json(null, 202);
    }

    public function deleteMenuItem(Request $request, int $itemId): JsonResponse
    {
        $item = MenuItem::with('section.menu.restaurant')->findOrFail($itemId);
        Gate::authorize('update', $item->section->menu->restaurant);

        $this->deleteImageFiles($item->image);
        $item->update(['image' => null]);

        return response()->json(null, 204);
    }

    private function deleteImageFiles(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = config('image.disk');
        $processor = app(ImageProcessor::class);

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        $thumb = $processor->thumbPath($path);
        if (Storage::disk($disk)->exists($thumb)) {
            Storage::disk($disk)->delete($thumb);
        }
    }
}
