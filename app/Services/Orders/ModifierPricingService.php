<?php

namespace App\Services\Orders;

use App\Enums\ModifierPricingMode;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Support\Collection;

/**
 * Recomputes an order line's unit price from the menu graph + the chosen
 * selection tree (the client never sends prices) and produces the recursive
 * order_item_modifiers snapshot.
 *
 * Composition: a top-level `replace` group's chosen option price is the
 * ABSOLUTE base (else the dish price_value); every `add` selection adds
 * option.price * chargeable-qty * portion-fraction; nested children always add
 * onto their parent option. Assumes the selection tree has already passed
 * {@see OrderSelectionValidator}.
 */
class ModifierPricingService
{
    /**
     * @param  list<array<string, mixed>>  $selections
     * @return array{unit_price: float, nodes: list<array<string, mixed>>}
     */
    public function price(MenuItem $item, array $selections, string $locale): array
    {
        $base = (float) ($item->price_value ?? 0);
        $result = $this->priceLevel($item->modifierGroups, $selections, $locale, $base);

        $unit = ($result['absolute'] ?? $base) + $result['additive'];

        return [
            'unit_price' => round($unit, 2),
            'nodes' => $result['nodes'],
        ];
    }

    /**
     * @param  Collection<int, ModifierGroup>  $availableGroups
     * @param  list<array<string, mixed>>  $selections
     * @return array{absolute: float|null, additive: float, nodes: list<array<string, mixed>>}
     */
    private function priceLevel(Collection $availableGroups, array $selections, string $locale, float $base): array
    {
        $groupsById = $availableGroups->keyBy('id');
        $freeOptionIds = $this->freeOptionIds($availableGroups, $selections);

        $absolute = null;
        $additive = 0.0;
        $nodes = [];

        foreach ($selections as $index => $selection) {
            /** @var ModifierGroup $group */
            $group = $groupsById->get((int) $selection['group_id']);
            /** @var ModifierOption $option */
            $option = $group->options->firstWhere('id', (int) $selection['option_id']);

            $qty = max(1, (int) ($selection['qty'] ?? 1));
            $portionNumerator = $selection['portion_numerator'] ?? null;
            $portionDenominator = $portionNumerator !== null ? max(1, (int) $group->portion_denominator) : null;
            $portionFraction = $portionNumerator !== null ? ((int) $portionNumerator / $portionDenominator) : 1.0;

            $isReplace = $group->pricing_mode === ModifierPricingMode::Replace;
            $ownUnit = $isReplace
                ? ($option->price !== null ? (float) $option->price : $base)
                : (float) ($option->price ?? 0);

            $chargeableQty = in_array($option->id, $freeOptionIds[$group->id] ?? [], true) ? 0 : $qty;
            $ownAmount = $ownUnit * $chargeableQty * $portionFraction;

            $children = is_array($selection['children'] ?? null) ? $selection['children'] : [];
            $childResult = $children === []
                ? ['additive' => 0.0, 'nodes' => []]
                : $this->priceLevel($option->childGroups, $children, $locale, $ownUnit);
            $childrenAmount = $childResult['additive'];

            $lineAmount = $ownAmount + $childrenAmount;

            if ($isReplace) {
                if ($absolute === null) {
                    $absolute = $ownAmount;
                }
                // Children of a replace option still add on top of the base.
                $additive += $childrenAmount;
            } else {
                $additive += $lineAmount;
            }

            $nodes[] = [
                'modifier_group_id' => $group->id,
                'modifier_option_id' => $option->id,
                'group_name_snapshot' => $group->translate('name', $locale),
                'option_name_snapshot' => $option->translate('name', $locale),
                'pricing_mode_snapshot' => $group->pricing_mode,
                'qty' => $qty,
                'portion_numerator' => $portionNumerator !== null ? (int) $portionNumerator : null,
                'portion_denominator' => $portionDenominator,
                'unit_price_snapshot' => round($ownUnit, 2),
                'line_amount_snapshot' => round($lineAmount, 2),
                'sort_order' => $index,
                'children' => $childResult['nodes'],
            ];
        }

        return ['absolute' => $absolute, 'additive' => $additive, 'nodes' => $nodes];
    }

    /**
     * For each `add` group with a `charge_above` threshold, the cheapest N
     * chosen options are free (Uber "free N then charge"). Returns the free
     * option ids keyed by group id.
     *
     * @param  Collection<int, ModifierGroup>  $availableGroups
     * @param  list<array<string, mixed>>  $selections
     * @return array<int, list<int>>
     */
    private function freeOptionIds(Collection $availableGroups, array $selections): array
    {
        $free = [];
        foreach ($availableGroups as $group) {
            if ($group->charge_above === null || $group->pricing_mode !== ModifierPricingMode::Add) {
                continue;
            }
            $chosen = collect($selections)
                ->filter(fn ($s) => (int) $s['group_id'] === $group->id)
                ->map(fn ($s) => $group->options->firstWhere('id', (int) $s['option_id']))
                ->filter()
                ->sortBy(fn (ModifierOption $o) => (float) ($o->price ?? 0))
                ->take((int) $group->charge_above);

            $free[$group->id] = $chosen->pluck('id')->all();
        }

        return $free;
    }
}
