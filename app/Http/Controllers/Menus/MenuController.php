<?php

namespace App\Http\Controllers\Menus;

use App\Actions\ChangeMenuSourceLocaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menus\StoreMenuRequest;
use App\Http\Requests\Menus\UpdateMenuRequest;
use App\Http\Resources\Menus\FullMenuResource;
use App\Http\Resources\Menus\MenuResource;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;

class MenuController extends Controller
{
    /**
     * List the menu(s) for a restaurant. After the multi-menu collapse, every
     * restaurant has at most one menu — the response is a collection of 0 or 1.
     */
    public function index(Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('view', $restaurant);

        $menus = $restaurant->menu()->get();

        return MenuResource::collection($menus);
    }

    /**
     * Return a full menu tree: sections → items → option groups → options (with translations).
     */
    public function full(Request $request, Menu $menu): FullMenuResource
    {
        Gate::authorize('view', $menu);

        $menu->load([
            'restaurant',
            'sections.icon',
            'sections.translations',
            'sections.items.translations',
            'sections.items.modifierGroups.translations',
            'sections.items.modifierGroups.options.translations',
        ]);

        $confidenceMap = [];
        if ($request->boolean('confidence')) {
            $raw = Redis::getdel('menu:confidence:'.$menu->id);
            if ($raw) {
                $confidenceMap = json_decode($raw, true) ?? [];
            }
        }

        return new FullMenuResource($menu, $confidenceMap);
    }

    /**
     * Create a new menu for a restaurant.
     */
    public function store(StoreMenuRequest $request, Restaurant $restaurant): JsonResponse
    {
        Gate::authorize('create', [Menu::class, $restaurant]);

        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            ...$request->validated(),
        ]);

        return (new MenuResource($menu))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a menu.
     */
    public function update(UpdateMenuRequest $request, Menu $menu, ChangeMenuSourceLocaleAction $changeSourceLocale): MenuResource
    {
        Gate::authorize('update', $menu);

        $validated = $request->validated();

        // Changing the original language is not a plain column write: it must
        // re-point the is_initial flag across all entities (and validate the
        // target is fully translated). Route it through the action.
        if (array_key_exists('source_locale', $validated)) {
            $changeSourceLocale($menu, (string) $validated['source_locale']);
            unset($validated['source_locale']);
            $menu->refresh();
        }

        if (! empty($validated)) {
            $menu->update($validated);
        }

        return new MenuResource($menu->fresh());
    }

    /**
     * Delete a menu.
     */
    public function destroy(Menu $menu): JsonResponse
    {
        Gate::authorize('delete', $menu);

        $menu->delete();

        return response()->json(null, 204);
    }

    /**
     * Search menu items across all translations.
     */
    public function search(Request $request, Menu $menu): JsonResponse
    {
        Gate::authorize('view', $menu);

        $q = trim($request->string('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $matchedIds = Translation::query()
            ->where('translatable_type', MenuItem::class)
            ->where('value', 'ilike', "%{$q}%")
            ->distinct()
            ->pluck('translatable_id');

        $items = MenuItem::whereIn('id', $matchedIds)
            ->whereHas('section', fn ($query) => $query->where('menu_id', $menu->id))
            ->with(['initialTranslations', 'section.initialTranslations'])
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $items->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'section_id' => $item->section_id,
                'section_name' => $item->section->name,
                'price' => $item->price_original_text,
            ]),
        ]);
    }
}
