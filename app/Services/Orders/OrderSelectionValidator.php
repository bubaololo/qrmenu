<?php

namespace App\Services\Orders;

use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Server-authoritative validation of a recursive modifier-selection tree
 * against an item's modifier graph. Closes the holes the old `exists:`-only
 * request left open: every option must belong to a group attached to THIS item
 * (no cross-menu injection), per-group cardinality (effective min/max, honoring
 * per-item pivot overrides) must hold, quantities must respect max_qty, and
 * children are only valid under a chosen option that opens that group.
 *
 * Shared by the public order request and the staff-side order builder.
 */
class OrderSelectionValidator
{
    public const MAX_DEPTH = 5;

    /**
     * @param  list<array<string, mixed>>  $selections
     */
    public function validate(MenuItem $item, array $selections, string $errorKey): void
    {
        $this->validateLevel($item->modifierGroups, $selections, $errorKey, 1);
    }

    /**
     * @param  Collection<int, ModifierGroup>  $availableGroups
     * @param  list<array<string, mixed>>  $selections
     */
    private function validateLevel(Collection $availableGroups, array $selections, string $errorKey, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw ValidationException::withMessages([$errorKey => 'Modifier nesting is too deep.']);
        }

        $groupsById = $availableGroups->keyBy('id');
        $countByGroup = [];

        foreach ($selections as $selection) {
            $groupId = (int) ($selection['group_id'] ?? 0);
            $optionId = (int) ($selection['option_id'] ?? 0);

            $group = $groupsById->get($groupId);
            if (! $group) {
                throw ValidationException::withMessages([
                    $errorKey => "Modifier group {$groupId} is not available for this item.",
                ]);
            }

            /** @var ModifierOption|null $option */
            $option = $group->options->firstWhere('id', $optionId);
            if (! $option) {
                throw ValidationException::withMessages([
                    $errorKey => "Option {$optionId} does not belong to modifier group {$groupId}.",
                ]);
            }

            $qty = (int) ($selection['qty'] ?? 1);
            if ($qty < 1 || $qty > max(1, (int) $option->max_qty)) {
                throw ValidationException::withMessages([
                    $errorKey => "Invalid quantity {$qty} for option {$optionId}.",
                ]);
            }

            $this->validatePortion($group, $selection, $errorKey);

            $countByGroup[$groupId] = ($countByGroup[$groupId] ?? 0) + 1;

            $children = $selection['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $this->validateLevel($option->childGroups, $children, $errorKey, $depth + 1);
            }
        }

        foreach ($availableGroups as $group) {
            $count = $countByGroup[$group->id] ?? 0;
            $min = $this->effectiveMin($group);
            $max = $this->effectiveMax($group);

            if ($count < $min) {
                throw ValidationException::withMessages([
                    $errorKey => "Modifier group {$group->id} requires at least {$min} selection(s).",
                ]);
            }
            if ($max !== null && $count > $max) {
                throw ValidationException::withMessages([
                    $errorKey => "Modifier group {$group->id} allows at most {$max} selection(s).",
                ]);
            }
            if ($group->selection_type === 'single' && $count > 1) {
                throw ValidationException::withMessages([
                    $errorKey => "Modifier group {$group->id} allows only one selection.",
                ]);
            }
        }
    }

    private function validatePortion(ModifierGroup $group, array $selection, string $errorKey): void
    {
        $numerator = $selection['portion_numerator'] ?? null;
        if ($numerator === null) {
            return;
        }
        $numerator = (int) $numerator;
        if ($group->selection_type !== 'portion' || $numerator < 1 || $numerator > max(1, (int) $group->portion_denominator)) {
            throw ValidationException::withMessages([
                $errorKey => "Invalid portion for modifier group {$group->id}.",
            ]);
        }
    }

    private function effectiveMin(ModifierGroup $group): int
    {
        $override = $group->pivot->selection_min_override ?? null;

        return (int) ($override ?? $group->selection_min);
    }

    private function effectiveMax(ModifierGroup $group): ?int
    {
        $override = $group->pivot->selection_max_override ?? null;
        $value = $override ?? $group->selection_max;

        return $value === null ? null : (int) $value;
    }
}
