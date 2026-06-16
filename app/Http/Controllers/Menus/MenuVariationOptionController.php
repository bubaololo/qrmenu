<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Menus\Concerns\ResolvesLocale;
use App\Http\Requests\Menus\StoreMenuVariationOptionRequest;
use App\Http\Requests\Menus\UpdateMenuVariationOptionRequest;
use App\Http\Resources\Menus\MenuVariationOptionResource;
use App\Models\MenuVariation;
use App\Models\MenuVariationOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MenuVariationOptionController extends Controller
{
    use ResolvesLocale;

    /**
     * Create an option in a variation. Price is absolute (replaces the dish price).
     */
    public function store(StoreMenuVariationOptionRequest $request, MenuVariation $menuVariation): JsonResponse
    {
        Gate::authorize('update', $menuVariation->menu);

        $validated = $request->validated();

        $option = MenuVariationOption::create([
            'variation_id' => $menuVariation->id,
            'price' => $validated['price'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        [$locale, $isInitial] = $this->resolveLocale($menuVariation->menu);
        $option->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);

        return (new MenuVariationOptionResource($option->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an option.
     */
    public function update(UpdateMenuVariationOptionRequest $request, MenuVariationOption $menuVariationOption): MenuVariationOptionResource
    {
        Gate::authorize('update', $menuVariationOption->variation->menu);

        $validated = $request->validated();

        if (isset($validated['name'])) {
            [$locale, $isInitial] = $this->resolveLocale($menuVariationOption->variation->menu);
            $menuVariationOption->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);
            unset($validated['name']);
        }

        if (! empty($validated)) {
            $menuVariationOption->update($validated);
        }

        return new MenuVariationOptionResource($menuVariationOption->fresh());
    }

    /**
     * Delete an option.
     */
    public function destroy(MenuVariationOption $menuVariationOption): JsonResponse
    {
        Gate::authorize('delete', $menuVariationOption->variation->menu);

        $menuVariationOption->delete();

        return response()->json(null, 204);
    }
}
