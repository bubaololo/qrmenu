<?php

namespace App\Actions\Orders;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\MenuOptionGroupOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Services\AnalysisEventBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceOrderAction
{
    public function __construct(private readonly AnalysisEventBroker $broker) {}

    /**
     * @param  array{
     *     restaurant_uniqid: string,
     *     table_uniqid: string,
     *     note?: ?string,
     *     items: list<array{
     *         menu_item_id: int,
     *         variation_option_id?: ?int,
     *         quantity: int,
     *         note?: ?string,
     *         selected_options?: list<array{group_id: int, option_ids: list<int>}>
     *     }>
     * }  $payload
     */
    public function __invoke(array $payload, ?string $guestToken): Order
    {
        $restaurant = Restaurant::where('uniqid', $payload['restaurant_uniqid'])->first();
        if (! $restaurant) {
            throw ValidationException::withMessages(['restaurant_uniqid' => 'Restaurant not found.']);
        }

        $table = DiningTable::where('uniqid', $payload['table_uniqid'])
            ->whereHas('zone', fn ($q) => $q->where('restaurant_id', $restaurant->id))
            ->first();
        if (! $table) {
            throw ValidationException::withMessages(['table_uniqid' => 'Table not found in this restaurant.']);
        }

        $menu = $restaurant->menu;
        if (! $menu) {
            throw ValidationException::withMessages(['restaurant_uniqid' => 'Restaurant has no menu yet.']);
        }

        $menuItemIds = array_column($payload['items'], 'menu_item_id');
        $menuItems = MenuItem::whereIn('id', $menuItemIds)
            ->where('is_visible', true)
            ->where('is_orderable', true)
            ->whereHas('section', fn ($q) => $q->where('menu_id', $menu->id)->where('is_active', true))
            ->get()
            ->keyBy('id');

        if ($menuItems->count() !== count(array_unique($menuItemIds))) {
            throw ValidationException::withMessages([
                'items' => 'One or more menu items are unavailable, hidden, or do not belong to the active menu.',
            ]);
        }

        $variationOptionIds = array_filter(array_column($payload['items'], 'variation_option_id'));
        $variationOptions = $variationOptionIds === []
            ? collect()
            : MenuOptionGroupOption::whereIn('id', $variationOptionIds)->get()->keyBy('id');

        $token = $guestToken !== null && Str::isUuid($guestToken)
            ? $guestToken
            : (string) Str::uuid();

        return DB::transaction(function () use ($restaurant, $table, $payload, $menuItems, $variationOptions, $token) {
            $bill = Bill::query()
                ->where('dining_table_id', $table->id)
                ->where('status', BillStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if (! $bill) {
                $bill = Bill::create([
                    'dining_table_id' => $table->id,
                    'status' => BillStatus::Open,
                    'currency' => $restaurant->currency ?? 'USD',
                    'opened_at' => now(),
                ]);
            }

            $order = Order::create([
                'bill_id' => $bill->id,
                'guest_token' => $token,
                'status' => OrderStatus::Pending,
                'note' => $payload['note'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($payload['items'] as $entry) {
                $menuItem = $menuItems[$entry['menu_item_id']];
                $variation = $entry['variation_option_id'] ?? null;
                $variationOption = $variation !== null ? ($variationOptions[$variation] ?? null) : null;

                $unitPrice = (float) ($menuItem->price_value ?? 0);
                if ($variationOption) {
                    $unitPrice += (float) $variationOption->price_adjust;
                }

                if (! empty($entry['selected_options'])) {
                    $unitPrice += $this->sumSelectedOptionAdjustments($entry['selected_options']);
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'variation_option_id' => $variationOption?->id,
                    'quantity' => $entry['quantity'],
                    'unit_price' => round($unitPrice, 2),
                    'currency' => $bill->currency,
                    'selected_options' => $entry['selected_options'] ?? null,
                    'note' => $entry['note'] ?? null,
                ]);
            }

            $order->load(['items', 'bill.diningTable']);

            $this->broker->publish(
                "restaurant-orders.{$restaurant->id}",
                'order.placed',
                [
                    'order_id' => $order->id,
                    'bill_id' => $bill->id,
                    'dining_table_id' => $table->id,
                    'guest_token' => $token,
                ],
            );

            return $order;
        });
    }

    /**
     * @param  list<array{group_id: int, option_ids: list<int>}>  $selectedOptions
     */
    private function sumSelectedOptionAdjustments(array $selectedOptions): float
    {
        $optionIds = [];
        foreach ($selectedOptions as $group) {
            foreach ($group['option_ids'] as $id) {
                $optionIds[] = $id;
            }
        }

        if ($optionIds === []) {
            return 0.0;
        }

        return (float) MenuOptionGroupOption::whereIn('id', $optionIds)->sum('price_adjust');
    }
}
