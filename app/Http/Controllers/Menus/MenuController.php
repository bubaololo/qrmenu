<?php

namespace App\Http\Controllers\Menus;

use App\Actions\CloneMenuAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menus\StoreMenuRequest;
use App\Http\Requests\Menus\UpdateMenuRequest;
use App\Http\Resources\Menus\FullMenuResource;
use App\Http\Resources\Menus\MenuResource;

use App\Models\Menu;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MenuController extends Controller
{
    /**
     * List all menus for a restaurant.
     */
    public function index(Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('view', $restaurant);

        return MenuResource::collection(
            $restaurant->menus()->orderByDesc('created_at')->get()
        );
    }

    /**
     * Return a full menu tree: sections → items → option groups → options (with translations).
     */
    public function full(Menu $menu): FullMenuResource
    {
        Gate::authorize('view', $menu);

        $menu->load([
            'restaurant',
            'sections.translations',
            'sections.items.translations',
            'sections.items.optionGroups.translations',
            'sections.items.optionGroups.options.translations',
        ]);

        return new FullMenuResource($menu);
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
    public function update(UpdateMenuRequest $request, Menu $menu): MenuResource
    {
        Gate::authorize('update', $menu);

        $menu->update($request->validated());

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
     * Activate a menu and deactivate all siblings.
     */
    public function activate(Menu $menu): MenuResource
    {
        Gate::authorize('update', $menu);

        $menu->activate();

        return new MenuResource($menu->fresh());
    }

    /**
     * Clone a menu (deep copy with sections, items, groups, translations).
     */
    public function clone(Menu $menu): JsonResponse
    {
        Gate::authorize('update', $menu);

        $cloned = (new CloneMenuAction)->handle($menu);

        return (new MenuResource($cloned))
            ->response()
            ->setStatusCode(201);
    }
}
