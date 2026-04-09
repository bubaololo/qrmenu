<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menus\AttachDetachItemsRequest;
use App\Http\Requests\Menus\StoreMenuOptionGroupRequest;
use App\Http\Requests\Menus\UpdateMenuOptionGroupRequest;
use App\Http\Resources\Menus\MenuOptionGroupResource;
use App\Models\MenuOptionGroup;
use App\Models\MenuSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MenuOptionGroupController extends Controller
{
    /**
     * List all option groups for a section.
     */
    public function index(MenuSection $menuSection): AnonymousResourceCollection
    {
        Gate::authorize('view', $menuSection->menu);

        return MenuOptionGroupResource::collection($menuSection->optionGroups);
    }

    /**
     * Show a single option group.
     */
    public function show(MenuOptionGroup $menuOptionGroup): MenuOptionGroupResource
    {
        Gate::authorize('view', $menuOptionGroup->section->menu);

        return new MenuOptionGroupResource($menuOptionGroup);
    }

    /**
     * Create an option group in a section.
     */
    public function store(StoreMenuOptionGroupRequest $request, MenuSection $menuSection): JsonResponse
    {
        Gate::authorize('update', $menuSection->menu);

        $validated = $request->validated();

        $group = MenuOptionGroup::create([
            'section_id' => $menuSection->id,
            'type' => $validated['type'] ?? null,
            'is_variation' => $validated['is_variation'] ?? false,
            'required' => $validated['required'] ?? false,
            'allow_multiple' => $validated['allow_multiple'] ?? false,
            'min_select' => $validated['min_select'] ?? 0,
            'max_select' => $validated['max_select'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $locale = $menuSection->menu->source_locale ?? 'und';
        $group->setTranslation('name', $locale, $validated['name'], isInitial: true);

        return (new MenuOptionGroupResource($group->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an option group.
     */
    public function update(UpdateMenuOptionGroupRequest $request, MenuOptionGroup $menuOptionGroup): MenuOptionGroupResource
    {
        Gate::authorize('update', $menuOptionGroup->section->menu);

        $validated = $request->validated();

        if (isset($validated['name'])) {
            $locale = $menuOptionGroup->section->menu->source_locale ?? 'und';
            $menuOptionGroup->setTranslation('name', $locale, $validated['name'], isInitial: true);
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
        Gate::authorize('delete', $menuOptionGroup->section->menu);

        $menuOptionGroup->delete();

        return response()->json(null, 204);
    }

    /**
     * Attach items to an option group via pivot.
     */
    public function attachItems(AttachDetachItemsRequest $request, MenuOptionGroup $menuOptionGroup): MenuOptionGroupResource
    {
        Gate::authorize('update', $menuOptionGroup->section->menu);

        $sectionItemIds = $menuOptionGroup->section->items()->pluck('id')->all();
        $validIds = array_intersect($request->validated('item_ids'), $sectionItemIds);

        $menuOptionGroup->items()->syncWithoutDetaching($validIds);

        return new MenuOptionGroupResource($menuOptionGroup->fresh());
    }

    /**
     * Detach items from an option group.
     */
    public function detachItems(AttachDetachItemsRequest $request, MenuOptionGroup $menuOptionGroup): MenuOptionGroupResource
    {
        Gate::authorize('update', $menuOptionGroup->section->menu);

        $menuOptionGroup->items()->detach($request->validated('item_ids'));

        return new MenuOptionGroupResource($menuOptionGroup->fresh());
    }
}
