<?php

namespace App\Actions\Orders;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Restaurant;
use App\Services\AnalysisEventBroker;
use App\Services\Orders\ModifierPricingService;
use App\Services\Orders\OrderSelectionValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceOrderAction
{
    public function __construct(
        private readonly AnalysisEventBroker $broker,
        private readonly OrderSelectionValidator $validator,
        private readonly ModifierPricingService $pricing,
    ) {}

    /**
     * @param  array{
     *     restaurant_uniqid: string,
     *     table_uniqid: string,
     *     note?: ?string,
     *     items: list<array{
     *         menu_item_id: int,
     *         quantity: int,
     *         note?: ?string,
     *         selections?: list<array<string, mixed>>
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
        $menuItems = MenuItem::with([
            'modifierGroups.options',
            'modifierGroups.translations',
            'modifierGroups.options.translations',
            'modifierGroups.options.driverPrices',
        ])
            ->whereIn('id', $menuItemIds)
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

        $locale = request()->attributes->get('locale_from_header')
            ?: ($menu->source_locale && $menu->source_locale !== 'mixed' ? $menu->source_locale : config('app.locale', 'en'));

        // Validate every line's selection tree against the menu graph before
        // touching the database (ownership, cardinality, quantities).
        foreach ($payload['items'] as $index => $entry) {
            $item = $menuItems[$entry['menu_item_id']];
            $this->validator->validate($item, $entry['selections'] ?? [], "items.{$index}");
        }

        $token = $guestToken !== null && Str::isUuid($guestToken)
            ? $guestToken
            : (string) Str::uuid();

        return DB::transaction(function () use ($restaurant, $table, $payload, $menuItems, $token, $locale) {
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
                $priced = $this->pricing->price($menuItem, $entry['selections'] ?? [], $locale);

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $entry['quantity'],
                    'unit_price' => $priced['unit_price'],
                    'currency' => $bill->currency,
                    'note' => $entry['note'] ?? null,
                ]);

                $this->persistModifierNodes($orderItem->id, null, $priced['nodes']);
            }

            $order->load(['items.modifiers', 'bill.diningTable']);

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
     * Persist the recursive modifier snapshot rows depth-first.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    private function persistModifierNodes(int $orderItemId, ?int $parentId, array $nodes): void
    {
        foreach ($nodes as $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);

            $row = OrderItemModifier::create([
                'order_item_id' => $orderItemId,
                'parent_id' => $parentId,
                ...$node,
            ]);

            if (is_array($children) && $children !== []) {
                $this->persistModifierNodes($orderItemId, $row->id, $children);
            }
        }
    }
}
