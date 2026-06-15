<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Menus\Concerns\ResolvesLocale;
use App\Http\Requests\Menus\AttachDetachItemsRequest;
use App\Http\Requests\Menus\StoreMenuOptionGroupRequest;
use App\Http\Requests\Menus\UpdateMenuOptionGroupRequest;
use App\Http\Resources\Menus\MenuOptionGroupResource;
use App\Models\Menu;
use App\Models\MenuOptionGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MenuOptionGroupController extends Controller
{
    use ResolvesLocale;

    /**
     * Create a variant/add-on group shared across the menu.
     */
    public function store(StoreMenuOptionGroupRequest $request, Menu $menu): JsonResponse
    {
        Gate::authorize('update', $menu);

        $validated = $request->validated();

        $group = MenuOptionGroup::create([
            'menu_id' => $menu->id,
            'type' => $validated['type'] ?? null,
            'kind' => $validated['kind'],
            'required' => $validated['required'] ?? false,
            'allow_multiple' => $validated['allow_multiple'] ?? false,
            'min_select' => $validated['min_select'] ?? 0,
            'max_select' => $validated['max_select'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        [$locale, $isInitial] = $this->resolveLocale($menu);
        $group->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);

        return (new MenuOptionGroupResource($group->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an option group.
     */
    public function update(UpdateMenuOptionGroupRequest $request, MenuOptionGroup $menuOptionGroup): MenuOptionGroupResource
    {
        Gate::authorize('update', $menuOptionGroup->menu);

        $validated = $request->validated();

        if (isset($validated['name'])) {
            [$locale, $isInitial] = $this->resolveLocale($menuOptionGroup->menu);
            $menuOptionGroup->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);
            unset($validated['name']);
        }

        if (! empty($validated)) {
            $menuOptionGroup->update($validated);
        }

        return new MenuOptionGroupResource($menuOptionGroup->fresh());
    }

    /**
     * Delete an option group.
     */
    public function destroy(MenuOptionGroup $menuOptionGroup): JsonResponse
    {
        Gate::authorize('delete', $menuOptionGroup->menu);

        $menuOptionGroup->delete();

        return response()->json(null, 204);
    }

    /**
     * Attach items to an option group via pivot.
     */
    public function attachItems(AttachDetachItemsRequest $request, MenuOptionGroup $menuOptionGroup): MenuOptionGroupResource
    {
        Gate::authorize('update', $menuOptionGroup->menu);

        $menuItemIds = $menuOptionGroup->menu->sections()
            ->with('items:id,section_id')
            ->get()
            ->flatMap->items
            ->pluck('id')
            ->all();
        $validIds = array_intersect($request->validated('item_ids'), $menuItemIds);

        $menuOptionGroup->items()->syncWithoutDetaching($validIds);

        return new MenuOptionGroupResource($menuOptionGroup->fresh());
    }

    /**
     * Detach items from an option group.
     */
    public function detachItems(AttachDetachItemsRequest $request, MenuOptionGroup $menuOptionGroup): MenuOptionGroupResource
    {
        Gate::authorize('update', $menuOptionGroup->menu);

        $menuOptionGroup->items()->detach($request->validated('item_ids'));

        return new MenuOptionGroupResource($menuOptionGroup->fresh());
    }
}
