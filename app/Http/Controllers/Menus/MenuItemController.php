<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menus\ReorderRequest;
use App\Http\Requests\Menus\StoreMenuItemRequest;
use App\Http\Requests\Menus\UpdateMenuItemRequest;
use App\Http\Resources\Menus\MenuItemResource;
use App\Models\MenuItem;
use App\Models\MenuSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MenuItemController extends Controller
{
    /**
     * List all items in a section.
     */
    public function index(MenuSection $menuSection): AnonymousResourceCollection
    {
        Gate::authorize('view', $menuSection->menu);

        return MenuItemResource::collection($menuSection->items);
    }

    /**
     * Show a single item.
     */
    public function show(MenuItem $menuItem): MenuItemResource
    {
        Gate::authorize('view', $menuItem->section->menu);

        return new MenuItemResource($menuItem);
    }

    /**
     * Create an item in a section.
     */
    public function store(StoreMenuItemRequest $request, MenuSection $menuSection): JsonResponse
    {
        Gate::authorize('update', $menuSection->menu);

        $validated = $request->validated();

        $item = MenuItem::create([
            'section_id' => $menuSection->id,
            'starred' => $validated['starred'] ?? false,
            'price_type' => $validated['price_type'] ?? null,
            'price_value' => $validated['price_value'] ?? null,
            'price_min' => $validated['price_min'] ?? null,
            'price_max' => $validated['price_max'] ?? null,
            'price_unit' => $validated['price_unit'] ?? null,
            'price_original_text' => $validated['price_original_text'] ?? '',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $locale = $menuSection->menu->source_locale ?? 'und';
        $item->setTranslation('name', $locale, $validated['name'], isInitial: true);

        if (isset($validated['description']) && $validated['description'] !== null) {
            $item->setTranslation('description', $locale, $validated['description'], isInitial: true);
        }

        return (new MenuItemResource($item->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an item.
     */
    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): MenuItemResource
    {
        Gate::authorize('update', $menuItem->section->menu);

        $validated = $request->validated();

        $locale = $menuItem->section->menu->source_locale ?? 'und';

        if (isset($validated['name'])) {
            $menuItem->setTranslation('name', $locale, $validated['name'], isInitial: true);
            unset($validated['name']);
        }

        if (array_key_exists('description', $validated)) {
            if ($validated['description'] !== null) {
                $menuItem->setTranslation('description', $locale, $validated['description'], isInitial: true);
            }
            unset($validated['description']);
        }

        if (! empty($validated)) {
            $menuItem->update($validated);
        }

        return new MenuItemResource($menuItem->fresh());
    }

    /**
     * Delete an item.
     */
    public function destroy(MenuItem $menuItem): JsonResponse
    {
        Gate::authorize('delete', $menuItem->section->menu);

        $menuItem->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk reorder items within a section.
     */
    public function reorder(ReorderRequest $request, MenuSection $menuSection): AnonymousResourceCollection
    {
        Gate::authorize('update', $menuSection->menu);

        $itemIds = $menuSection->items()->pluck('id')->all();

        foreach ($request->validated('order') as $entry) {
            if (in_array($entry['id'], $itemIds)) {
                MenuItem::where('id', $entry['id'])->update(['sort_order' => $entry['sort_order']]);
            }
        }

        return MenuItemResource::collection($menuSection->items()->orderBy('sort_order')->get());
    }
}
