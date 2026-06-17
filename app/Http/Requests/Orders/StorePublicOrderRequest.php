<?php

namespace App\Http\Requests\Orders;

use App\Services\Orders\OrderSelectionValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicOrderRequest extends FormRequest
{
    /** Hard cap on total modifier-selection nodes per item (payload-bomb guard). */
    private const MAX_NODES_PER_ITEM = 200;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'restaurant_uniqid' => ['required', 'string', 'min:8', 'max:32'],
            'table_uniqid' => ['required', 'string', 'min:8', 'max:32'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            // Recursive selection tree — shape only; ownership, cardinality and
            // quantity caps are enforced server-side against the menu graph in
            // PlaceOrderAction via OrderSelectionValidator.
            'items.*.selections' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $selections = $item['selections'] ?? [];
                if (! is_array($selections)) {
                    continue;
                }
                [$ok, $count] = $this->inspectTree($selections, 1);
                if (! $ok) {
                    $validator->errors()->add("items.{$index}.selections", 'Modifier selections are malformed or nested too deeply.');
                } elseif ($count > self::MAX_NODES_PER_ITEM) {
                    $validator->errors()->add("items.{$index}.selections", 'Too many modifier selections.');
                }
            }
        });
    }

    /**
     * Validate the recursive shape (each node has integer group_id/option_id,
     * optional integer qty/portion, optional children array) within the depth
     * cap, and count total nodes.
     *
     * @param  array<int, mixed>  $nodes
     * @return array{0: bool, 1: int}
     */
    private function inspectTree(array $nodes, int $depth): array
    {
        if ($depth > OrderSelectionValidator::MAX_DEPTH) {
            return [false, 0];
        }

        $count = 0;
        foreach ($nodes as $node) {
            if (! is_array($node)
                || ! isset($node['group_id'], $node['option_id'])
                || filter_var($node['group_id'], FILTER_VALIDATE_INT) === false
                || filter_var($node['option_id'], FILTER_VALIDATE_INT) === false) {
                return [false, 0];
            }
            $count++;

            $children = $node['children'] ?? [];
            if ($children !== [] && $children !== null) {
                if (! is_array($children)) {
                    return [false, 0];
                }
                [$ok, $childCount] = $this->inspectTree($children, $depth + 1);
                if (! $ok) {
                    return [false, 0];
                }
                $count += $childCount;
            }
        }

        return [true, $count];
    }
}
