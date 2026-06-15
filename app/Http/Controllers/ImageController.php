<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImageJob;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Zone;
use App\Services\ImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    public function updateRestaurant(Request $request, int $restaurantId): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:10240']]);

        $restaurant = Restaurant::findOrFail($restaurantId);
        Gate::authorize('update', $restaurant);

        return $this->storeAndDispatch(
            $request,
            Restaurant::class,
            $restaurant->id,
            $restaurant->id,
            config('image.paths.restaurants'),
            $restaurant->image,
            profile: 'banner',
        );
    }

    public function deleteRestaurant(Request $request, int $restaurantId): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($restaurantId);
        Gate::authorize('update', $restaurant);

        $this->deleteImageFiles($restaurant->image);
        $restaurant->update(['image' => null]);

        return response()->json(null, 204);
    }

    public function updateRestaurantLogo(Request $request, int $restaurantId): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:10240']]);

        $restaurant = Restaurant::findOrFail($restaurantId);
        Gate::authorize('update', $restaurant);

        return $this->storeAndDispatch(
            $request,
            Restaurant::class,
            $restaurant->id,
            $restaurant->id,
            config('image.paths.logos'),
            $restaurant->logo,
            'logo',
            profile: 'logo',
        );
    }

    public function deleteRestaurantLogo(Request $request, int $restaurantId): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($restaurantId);
        Gate::authorize('update', $restaurant);

        $this->deleteImageFiles($restaurant->logo);
        $restaurant->update(['logo' => null]);

        return response()->json(null, 204);
    }

    public function updateZone(Request $request, int $zoneId): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:10240']]);

        $zone = Zone::with('restaurant')->findOrFail($zoneId);
        Gate::authorize('update', $zone->restaurant);

        return $this->storeAndDispatch(
            $request,
            Zone::class,
            $zone->id,
            $zone->restaurant->id,
            config('image.paths.zones'),
            $zone->image,
        );
    }

    public function deleteZone(Request $request, int $zoneId): JsonResponse
    {
        $zone = Zone::with('restaurant')->findOrFail($zoneId);
        Gate::authorize('update', $zone->restaurant);

        $this->deleteImageFiles($zone->image);
        $zone->update(['image' => null]);

        return response()->json(null, 204);
    }

    public function updateMenuItem(Request $request, int $itemId): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:51200']]);

        $item = MenuItem::with('section.menu.restaurant')->findOrFail($itemId);
        Gate::authorize('update', $item->section->menu->restaurant);

        return $this->storeAndDispatch(
            $request,
            MenuItem::class,
            $item->id,
            $item->section->menu->restaurant_id,
            // Nest under the menu id, matching CropMenuItemImagesJob, so a menu's
            // photos live together in menu-items/{menu_id}/ instead of the root.
            config('image.paths.menu_items').'/'.$item->section->menu_id,
            $item->image,
        );
    }

    public function deleteMenuItem(Request $request, int $itemId): JsonResponse
    {
        $item = MenuItem::with('section.menu.restaurant')->findOrFail($itemId);
        Gate::authorize('update', $item->section->menu->restaurant);

        $this->deleteImageFiles($item->image);
        $item->update(['image' => null]);

        return response()->json(null, 204);
    }

    private function storeAndDispatch(
        Request $request,
        string $modelClass,
        int $modelId,
        int $restaurantId,
        string $targetDir,
        ?string $oldImagePath,
        string $fieldName = 'image',
        string $profile = 'default',
    ): JsonResponse {
        $originalsDisk = config('image.originals_disk');
        $disk = config('image.disk');
        $format = config('image.format');
        $baseName = Str::uuid()->toString();

        $tempPath = $request->file('image')->store(
            config('image.paths.originals'),
            $originalsDisk,
        );

        ProcessImageJob::dispatch($modelClass, $modelId, $restaurantId, $tempPath, $targetDir, $baseName, $oldImagePath, $fieldName, $profile);

        return response()->json(['data' => [
            'image_url' => Storage::disk($disk)->url("{$targetDir}/{$baseName}.{$format}"),
            'thumb_url' => Storage::disk($disk)->url("{$targetDir}/{$baseName}_thumb.{$format}"),
        ]], 202);
    }

    private function deleteImageFiles(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = config('image.disk');
        $processor = app(ImageProcessor::class);

        Storage::disk($disk)->delete($path);
        Storage::disk($disk)->delete($processor->thumbPath($path));
    }
}
