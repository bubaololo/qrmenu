<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Menus\Concerns\ResolvesLocale;
use App\Http\Requests\Menus\StoreModifierOptionRequest;
use App\Http\Requests\Menus\UpdateModifierOptionRequest;
use App\Http\Resources\Menus\ModifierOptionResource;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ModifierOptionController extends Controller
{
    use ResolvesLocale;

    /**
     * Create an option within a group. Price meaning follows the group's
     * pricing_mode (absolute when replace, delta when add).
     */
    public function store(StoreModifierOptionRequest $request, ModifierGroup $modifierGroup): JsonResponse
    {
        Gate::authorize('update', $modifierGroup->menu);

        $validated = $request->validated();

        $option = ModifierOption::create([
            'group_id' => $modifierGroup->id,
            'price' => $validated['price'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'default_qty' => $validated['default_qty'] ?? 1,
            'max_qty' => $validated['max_qty'] ?? 1,
            'linked_menu_item_id' => $validated['linked_menu_item_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        [$locale, $isInitial] = $this->resolveLocale($modifierGroup->menu);
        $option->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);

        return (new ModifierOptionResource($option->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an option.
     */
    public function update(UpdateModifierOptionRequest $request, ModifierOption $modifierOption): ModifierOptionResource
    {
        Gate::authorize('update', $modifierOption->group->menu);

        $validated = $request->validated();

        if (isset($validated['name'])) {
            [$locale, $isInitial] = $this->resolveLocale($modifierOption->group->menu);
            $modifierOption->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);
            unset($validated['name']);
        }

        if (! empty($validated)) {
            $modifierOption->update($validated);
        }

        return new ModifierOptionResource($modifierOption->fresh());
    }

    /**
     * Delete an option (and, via cascade, any nested groups it opens).
     */
    public function destroy(ModifierOption $modifierOption): JsonResponse
    {
        Gate::authorize('delete', $modifierOption->group->menu);

        $modifierOption->delete();

        return response()->json(null, 204);
    }
}
