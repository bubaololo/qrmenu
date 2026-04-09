<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menus\StoreMenuOptionGroupOptionRequest;
use App\Http\Requests\Menus\UpdateMenuOptionGroupOptionRequest;
use App\Http\Resources\Menus\MenuOptionGroupOptionResource;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MenuOptionGroupOptionController extends Controller
{
    /**
     * List all options for a group.
     */
    public function index(MenuOptionGroup $menuOptionGroup): AnonymousResourceCollection
    {
        Gate::authorize('view', $menuOptionGroup->section->menu);

        return MenuOptionGroupOptionResource::collection($menuOptionGroup->options);
    }

    /**
     * Show a single option.
     */
    public function show(MenuOptionGroupOption $menuOptionGroupOption): MenuOptionGroupOptionResource
    {
        Gate::authorize('view', $menuOptionGroupOption->group->section->menu);

        return new MenuOptionGroupOptionResource($menuOptionGroupOption);
    }

    /**
     * Create an option in a group.
     */
    public function store(StoreMenuOptionGroupOptionRequest $request, MenuOptionGroup $menuOptionGroup): JsonResponse
    {
        Gate::authorize('update', $menuOptionGroup->section->menu);

        $validated = $request->validated();

        $option = MenuOptionGroupOption::create([
            'group_id' => $menuOptionGroup->id,
            'price_adjust' => $validated['price_adjust'] ?? 0,
            'is_default' => $validated['is_default'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $locale = $menuOptionGroup->section->menu->source_locale ?? 'und';
        $option->setTranslation('name', $locale, $validated['name'], isInitial: true);

        return (new MenuOptionGroupOptionResource($option->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an option.
     */
    public function update(UpdateMenuOptionGroupOptionRequest $request, MenuOptionGroupOption $menuOptionGroupOption): MenuOptionGroupOptionResource
    {
        Gate::authorize('update', $menuOptionGroupOption->group->section->menu);

        $validated = $request->validated();

        if (isset($validated['name'])) {
            $locale = $menuOptionGroupOption->group->section->menu->source_locale ?? 'und';
            $menuOptionGroupOption->setTranslation('name', $locale, $validated['name'], isInitial: true);
            unset($validated['name']);
        }

        if (! empty($validated)) {
            $menuOptionGroupOption->update($validated);
        }

        return new MenuOptionGroupOptionResource($menuOptionGroupOption->fresh());
    }

    /**
     * Delete an option.
     */
    public function destroy(MenuOptionGroupOption $menuOptionGroupOption): JsonResponse
    {
        Gate::authorize('delete', $menuOptionGroupOption->group->section->menu);

        $menuOptionGroupOption->delete();

        return response()->json(null, 204);
    }
}
