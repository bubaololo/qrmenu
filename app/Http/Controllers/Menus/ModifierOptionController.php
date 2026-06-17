<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Menus\Concerns\ResolvesLocale;
use App\Http\Requests\Menus\StoreModifierOptionRequest;
use App\Http\Requests\Menus\UpdateModifierOptionRequest;
use App\Http\Resources\Menus\ModifierOptionResource;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\ModifierOptionPrice;
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
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        [$locale, $isInitial] = $this->resolveLocale($modifierGroup->menu);
        $option->setTranslation('name', $locale, $validated['name'], isInitial: $isInitial);

        if (array_key_exists('prices', $validated)) {
            $this->syncDriverPrices($option, $modifierGroup, $validated['prices']);
        }

        return (new ModifierOptionResource($option->fresh(['driverPrices'])))
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

        $prices = $validated['prices'] ?? null;
        unset($validated['prices']);

        if (! empty($validated)) {
            $modifierOption->update($validated);
        }

        if ($prices !== null) {
            $this->syncDriverPrices($modifierOption, $modifierOption->group, $prices);
        }

        return new ModifierOptionResource($modifierOption->fresh(['driverPrices']));
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

    /**
     * Sync an option's size-dependent price matrix. Only rows whose
     * `driver_option_id` belongs to the group's driver group are kept; entries
     * for other options are dropped. Removes rows no longer present.
     *
     * @param  list<array{driver_option_id: int, price: numeric}>  $prices
     */
    private function syncDriverPrices(ModifierOption $option, ModifierGroup $group, array $prices): void
    {
        $validDriverIds = $group->price_driver_group_id !== null
            ? ModifierOption::where('group_id', $group->price_driver_group_id)->pluck('id')->all()
            : [];

        $kept = [];
        foreach ($prices as $entry) {
            $driverId = (int) ($entry['driver_option_id'] ?? 0);
            if (! in_array($driverId, $validDriverIds, true)) {
                continue;
            }
            ModifierOptionPrice::updateOrCreate(
                ['option_id' => $option->id, 'driver_option_id' => $driverId],
                ['price' => $entry['price']],
            );
            $kept[] = $driverId;
        }

        ModifierOptionPrice::where('option_id', $option->id)
            ->when($kept !== [], fn ($q) => $q->whereNotIn('driver_option_id', $kept))
            ->delete();
    }
}
