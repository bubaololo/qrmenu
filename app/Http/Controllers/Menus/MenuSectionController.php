<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Menus\Concerns\ResolvesLocale;
use App\Http\Requests\Menus\ReorderRequest;
use App\Http\Requests\Menus\StoreMenuSectionRequest;
use App\Http\Requests\Menus\UpdateMenuSectionRequest;
use App\Http\Resources\Menus\MenuSectionResource;
use App\Models\Menu;
use App\Models\MenuSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MenuSectionController extends Controller
{
    use ResolvesLocale;
    /**
     * Create a section in a menu.
     */
    public function store(StoreMenuSectionRequest $request, Menu $menu): JsonResponse
    {
        Gate::authorize('update', $menu);

        $validated = $request->validated();

        $section = MenuSection::create([
            'menu_id' => $menu->id,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $locale = $menu->source_locale ?? 'und';
        $section->setTranslation('name', $locale, $validated['name'], isInitial: true);

        return (new MenuSectionResource($section->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a section.
     */
    public function update(UpdateMenuSectionRequest $request, MenuSection $menuSection): MenuSectionResource
    {
        Gate::authorize('update', $menuSection->menu);

        $validated = $request->validated();

        if (isset($validated['name'])) {
            $sourceLocale = $menuSection->menu->source_locale ?? 'und';
            [$locale, $isInitial] = $this->resolveLocale($sourceLocale);
            $menuSection->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);
            unset($validated['name']);
        }

        if (! empty($validated)) {
            $menuSection->update($validated);
        }

        return new MenuSectionResource($menuSection->fresh());
    }

    /**
     * Delete a section.
     */
    public function destroy(MenuSection $menuSection): JsonResponse
    {
        Gate::authorize('delete', $menuSection->menu);

        $menuSection->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk reorder sections within a menu.
     */
    public function reorder(ReorderRequest $request, Menu $menu): AnonymousResourceCollection
    {
        Gate::authorize('update', $menu);

        $sectionIds = $menu->sections()->pluck('id')->all();

        foreach ($request->validated('order') as $entry) {
            if (in_array($entry['id'], $sectionIds)) {
                MenuSection::where('id', $entry['id'])->update(['sort_order' => $entry['sort_order']]);
            }
        }

        return MenuSectionResource::collection($menu->sections()->orderBy('sort_order')->get());
    }
}
