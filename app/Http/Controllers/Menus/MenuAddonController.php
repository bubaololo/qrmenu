<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Menus\Concerns\ResolvesLocale;
use App\Http\Requests\Menus\AttachDetachItemsRequest;
use App\Http\Requests\Menus\StoreMenuAddonRequest;
use App\Http\Requests\Menus\UpdateMenuAddonRequest;
use App\Http\Resources\Menus\MenuAddonResource;
use App\Models\Menu;
use App\Models\MenuAddon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MenuAddonController extends Controller
{
    use ResolvesLocale;

    /**
     * Create an atomic add-on shared across the menu. Price is a delta (added on top).
     */
    public function store(StoreMenuAddonRequest $request, Menu $menu): JsonResponse
    {
        Gate::authorize('update', $menu);

        $validated = $request->validated();

        $addon = MenuAddon::create([
            'menu_id' => $menu->id,
            'price' => $validated['price'] ?? 0,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        [$locale, $isInitial] = $this->resolveLocale($menu);
        $addon->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);

        return (new MenuAddonResource($addon->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an add-on.
     */
    public function update(UpdateMenuAddonRequest $request, MenuAddon $menuAddon): MenuAddonResource
    {
        Gate::authorize('update', $menuAddon->menu);

        $validated = $request->validated();

        if (isset($validated['name'])) {
            [$locale, $isInitial] = $this->resolveLocale($menuAddon->menu);
            $menuAddon->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);
            unset($validated['name']);
        }

        if (! empty($validated)) {
            $menuAddon->update($validated);
        }

        return new MenuAddonResource($menuAddon->fresh());
    }

    /**
     * Delete an add-on.
     */
    public function destroy(MenuAddon $menuAddon): JsonResponse
    {
        Gate::authorize('delete', $menuAddon->menu);

        $menuAddon->delete();

        return response()->json(null, 204);
    }

    /**
     * Attach items to an add-on via pivot.
     */
    public function attachItems(AttachDetachItemsRequest $request, MenuAddon $menuAddon): MenuAddonResource
    {
        Gate::authorize('update', $menuAddon->menu);

        $menuItemIds = $menuAddon->menu->sections()
            ->with('items:id,section_id')
            ->get()
            ->flatMap->items
            ->pluck('id')
            ->all();
        $validIds = array_intersect($request->validated('item_ids'), $menuItemIds);

        $menuAddon->items()->syncWithoutDetaching($validIds);

        return new MenuAddonResource($menuAddon->fresh());
    }

    /**
     * Detach items from an add-on.
     */
    public function detachItems(AttachDetachItemsRequest $request, MenuAddon $menuAddon): MenuAddonResource
    {
        Gate::authorize('update', $menuAddon->menu);

        $menuAddon->items()->detach($request->validated('item_ids'));

        return new MenuAddonResource($menuAddon->fresh());
    }
}
