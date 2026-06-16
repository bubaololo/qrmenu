<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Menus\Concerns\ResolvesLocale;
use App\Http\Requests\Menus\AttachDetachItemsRequest;
use App\Http\Requests\Menus\StoreMenuVariationRequest;
use App\Http\Requests\Menus\UpdateMenuVariationRequest;
use App\Http\Resources\Menus\MenuVariationResource;
use App\Models\Menu;
use App\Models\MenuVariation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MenuVariationController extends Controller
{
    use ResolvesLocale;

    /**
     * Create a variation (a pick-exactly-one axis) shared across the menu.
     */
    public function store(StoreMenuVariationRequest $request, Menu $menu): JsonResponse
    {
        Gate::authorize('update', $menu);

        $validated = $request->validated();

        $variation = MenuVariation::create([
            'menu_id' => $menu->id,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        [$locale, $isInitial] = $this->resolveLocale($menu);
        $variation->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);

        return (new MenuVariationResource($variation->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a variation.
     */
    public function update(UpdateMenuVariationRequest $request, MenuVariation $menuVariation): MenuVariationResource
    {
        Gate::authorize('update', $menuVariation->menu);

        $validated = $request->validated();

        if (isset($validated['name'])) {
            [$locale, $isInitial] = $this->resolveLocale($menuVariation->menu);
            $menuVariation->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);
            unset($validated['name']);
        }

        if (! empty($validated)) {
            $menuVariation->update($validated);
        }

        return new MenuVariationResource($menuVariation->fresh());
    }

    /**
     * Delete a variation.
     */
    public function destroy(MenuVariation $menuVariation): JsonResponse
    {
        Gate::authorize('delete', $menuVariation->menu);

        $menuVariation->delete();

        return response()->json(null, 204);
    }

    /**
     * Attach items to a variation via pivot.
     */
    public function attachItems(AttachDetachItemsRequest $request, MenuVariation $menuVariation): MenuVariationResource
    {
        Gate::authorize('update', $menuVariation->menu);

        $menuItemIds = $menuVariation->menu->sections()
            ->with('items:id,section_id')
            ->get()
            ->flatMap->items
            ->pluck('id')
            ->all();
        $validIds = array_intersect($request->validated('item_ids'), $menuItemIds);

        $menuVariation->items()->syncWithoutDetaching($validIds);

        return new MenuVariationResource($menuVariation->fresh());
    }

    /**
     * Detach items from a variation.
     */
    public function detachItems(AttachDetachItemsRequest $request, MenuVariation $menuVariation): MenuVariationResource
    {
        Gate::authorize('update', $menuVariation->menu);

        $menuVariation->items()->detach($request->validated('item_ids'));

        return new MenuVariationResource($menuVariation->fresh());
    }
}
