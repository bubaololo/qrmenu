<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Menus\Concerns\ResolvesLocale;
use App\Http\Requests\Menus\AttachDetachItemsRequest;
use App\Http\Requests\Menus\StoreModifierGroupRequest;
use App\Http\Requests\Menus\UpdateItemModifierGroupRequest;
use App\Http\Requests\Menus\UpdateModifierGroupRequest;
use App\Http\Resources\Menus\ModifierGroupResource;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ModifierGroupController extends Controller
{
    use ResolvesLocale;

    /**
     * List a menu's top-level modifier groups (the library), each with its
     * options and a usage count (`items_count`). Request `?include=options`
     * to embed the options in the JSON:API document.
     */
    public function index(Menu $menu): AnonymousResourceCollection
    {
        Gate::authorize('view', $menu);

        $groups = $menu->modifierGroups()
            ->with(['translations', 'options.translations', 'options.driverPrices'])
            ->withCount('items')
            ->get();

        return ModifierGroupResource::collection($groups);
    }

    /**
     * Create a modifier group (Size, Extras, …) shared across the menu.
     */
    public function store(StoreModifierGroupRequest $request, Menu $menu): JsonResponse
    {
        Gate::authorize('update', $menu);

        $validated = $request->validated();
        $this->assertDriverGroup($validated, $menu);

        $group = ModifierGroup::create([
            'menu_id' => $menu->id,
            ...$this->groupAttributes($validated),
        ]);

        [$locale, $isInitial] = $this->resolveLocale($menu);
        $group->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);

        return (new ModifierGroupResource($group->fresh(['options'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a modifier group.
     */
    public function update(UpdateModifierGroupRequest $request, ModifierGroup $modifierGroup): ModifierGroupResource
    {
        Gate::authorize('update', $modifierGroup->menu);

        $validated = $request->validated();
        $this->assertDriverGroup($validated, $modifierGroup->menu, $modifierGroup->id);

        if (isset($validated['name'])) {
            [$locale, $isInitial] = $this->resolveLocale($modifierGroup->menu);
            $modifierGroup->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);
            unset($validated['name']);
        }

        $attributes = $this->groupAttributes($validated, $modifierGroup);
        if (! empty($attributes)) {
            $modifierGroup->update($attributes);
        }

        return new ModifierGroupResource($modifierGroup->fresh(['options']));
    }

    /**
     * Delete a modifier group (and, via cascade, its options and nested groups).
     */
    public function destroy(ModifierGroup $modifierGroup): JsonResponse
    {
        Gate::authorize('delete', $modifierGroup->menu);

        $modifierGroup->delete();

        return response()->json(null, 204);
    }

    /**
     * Attach items to a group via the pivot (scoped to the group's own menu).
     */
    public function attachItems(AttachDetachItemsRequest $request, ModifierGroup $modifierGroup): ModifierGroupResource
    {
        Gate::authorize('update', $modifierGroup->menu);

        $menuItemIds = $modifierGroup->menu->sections()
            ->with('items:id,section_id')
            ->get()
            ->flatMap->items
            ->pluck('id')
            ->all();
        $validIds = array_intersect($request->validated('item_ids'), $menuItemIds);

        $modifierGroup->items()->syncWithoutDetaching($validIds);

        return new ModifierGroupResource($modifierGroup->fresh(['options', 'items']));
    }

    /**
     * Detach items from a group.
     */
    public function detachItems(AttachDetachItemsRequest $request, ModifierGroup $modifierGroup): ModifierGroupResource
    {
        Gate::authorize('update', $modifierGroup->menu);

        $modifierGroup->items()->detach($request->validated('item_ids'));

        return new ModifierGroupResource($modifierGroup->fresh(['options', 'items']));
    }

    /**
     * Update the per-item override of an attached group's selection rule
     * (required / min / max / hidden / order) — does NOT touch the shared
     * group. `null` for an override means "inherit the group's default".
     */
    public function updateItemOverrides(UpdateItemModifierGroupRequest $request, MenuItem $menuItem, ModifierGroup $modifierGroup): ModifierGroupResource
    {
        $menuItem->loadMissing('section.menu');
        $menu = $menuItem->section->menu;

        Gate::authorize('update', $menu);

        abort_unless($modifierGroup->menu_id === $menu->id, 404);
        abort_unless($menuItem->modifierGroups()->whereKey($modifierGroup->getKey())->exists(), 404);

        $menuItem->modifierGroups()->updateExistingPivot($modifierGroup->id, $request->validated());

        return new ModifierGroupResource($modifierGroup->fresh(['options']));
    }

    /**
     * Guard the size-dependent driver: it must be a single-select group of the
     * same menu and not the group itself.
     *
     * @param  array<string, mixed>  $validated
     */
    private function assertDriverGroup(array $validated, Menu $menu, ?int $selfId = null): void
    {
        if (! array_key_exists('price_driver_group_id', $validated) || $validated['price_driver_group_id'] === null) {
            return;
        }

        $valid = ModifierGroup::query()
            ->whereKey($validated['price_driver_group_id'])
            ->where('menu_id', $menu->id)
            ->where('selection_type', 'single')
            ->when($selfId !== null, fn ($q) => $q->whereKeyNot($selfId))
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'price_driver_group_id' => 'The price driver must be a single-select group of the same menu.',
            ]);
        }
    }

    /**
     * Map validated input to group columns, keeping selection_min and `required`
     * in agreement (selection_min is authoritative — a required group needs at
     * least one selection).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function groupAttributes(array $validated, ?ModifierGroup $existing = null): array
    {
        $attributes = [];
        foreach (['pricing_mode', 'selection_type', 'selection_min', 'selection_max', 'required', 'charge_above', 'portion_denominator', 'price_driver_group_id', 'sort_order'] as $key) {
            if (array_key_exists($key, $validated)) {
                $attributes[$key] = $validated[$key];
            }
        }

        $min = $attributes['selection_min'] ?? $existing?->selection_min ?? 0;
        $required = array_key_exists('required', $attributes)
            ? (bool) $attributes['required']
            : ($existing?->required ?? false);

        if ($required) {
            $min = max(1, (int) $min);
        }
        $attributes['selection_min'] = (int) $min;
        $attributes['required'] = $min >= 1;

        return $attributes;
    }
}
