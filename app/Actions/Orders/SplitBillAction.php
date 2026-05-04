<?php

namespace App\Actions\Orders;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\AnalysisEventBroker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SplitBillAction
{
    public function __construct(private readonly AnalysisEventBroker $broker) {}

    /**
     * Split a bill into N closed bills based on `order_item_ids` groups.
     * Items not assigned to any split stay on the original bill (which closes too).
     *
     * @param  list<array{order_item_ids: list<int>}>  $splits
     * @return Collection<int, Bill> Newly created (and original, if it kept any items) closed bills
     */
    public function __invoke(Bill $bill, array $splits, ?User $closedBy = null): Collection
    {
        if ($bill->status === BillStatus::Closed) {
            throw ValidationException::withMessages(['status' => 'Bill is already closed.']);
        }

        $bill->load(['orders.items', 'diningTable.zone']);
        $billItems = $bill->orders->flatMap->items->keyBy('id');
        $restaurantId = $bill->diningTable?->zone?->restaurant_id;

        $this->validateSplitItems($billItems, $splits);

        $createdBills = DB::transaction(function () use ($bill, $splits, $billItems, $closedBy) {
            $bills = [];

            foreach ($splits as $split) {
                $bills[] = $this->createSplitBill($bill, $billItems, $split['order_item_ids'], $closedBy);
            }

            $remainingIds = array_merge(...array_column($splits, 'order_item_ids'));
            $remaining = $billItems->reject(fn (OrderItem $i) => in_array($i->id, $remainingIds, true));

            $bill->update([
                'status' => BillStatus::Closed,
                'closed_at' => now(),
                'closed_by_user_id' => $closedBy?->id,
                'total_amount' => $remaining->isNotEmpty() ? $this->sumLines($remaining) : 0,
            ]);

            if ($remaining->isNotEmpty()) {
                $bills[] = $bill->fresh();
            }

            return $bills;
        });

        if ($restaurantId !== null) {
            $this->broker->publish(
                "restaurant-orders.{$restaurantId}",
                'bill.split',
                [
                    'original_bill_id' => $bill->id,
                    'new_bill_ids' => array_map(fn (Bill $b) => $b->id, $createdBills),
                ],
            );
        }

        return collect($createdBills);
    }

    /**
     * @param  Collection<int, OrderItem>  $billItems
     * @param  list<array{order_item_ids: list<int>}>  $splits
     */
    private function validateSplitItems(Collection $billItems, array $splits): void
    {
        $allRequestedIds = [];
        foreach ($splits as $split) {
            foreach ($split['order_item_ids'] as $id) {
                if (! $billItems->has($id)) {
                    throw ValidationException::withMessages([
                        'splits' => "Order item {$id} does not belong to this bill.",
                    ]);
                }
                $allRequestedIds[] = $id;
            }
        }

        if (count($allRequestedIds) !== count(array_unique($allRequestedIds))) {
            throw ValidationException::withMessages([
                'splits' => 'Each order item must appear in only one split.',
            ]);
        }
    }

    /**
     * @param  Collection<int, OrderItem>  $billItems
     * @param  list<int>  $orderItemIds
     */
    private function createSplitBill(Bill $source, Collection $billItems, array $orderItemIds, ?User $closedBy): Bill
    {
        $items = $billItems->only($orderItemIds);

        $newBill = Bill::create([
            'dining_table_id' => $source->dining_table_id,
            'status' => BillStatus::Closed,
            'currency' => $source->currency,
            'opened_at' => $source->opened_at,
            'closed_at' => now(),
            'closed_by_user_id' => $closedBy?->id,
            'total_amount' => $this->sumLines($items),
        ]);

        // Move items to the new bill. Whole-order moves rebind `bill_id`; partial
        // moves clone the order so the original keeps the unmoved items.
        foreach ($items->pluck('order_id')->unique() as $orderId) {
            $itemsForOrder = $items->where('order_id', $orderId);
            $allMoved = $billItems->where('order_id', $orderId)->count() === $itemsForOrder->count();

            if ($allMoved) {
                Order::where('id', $orderId)->update(['bill_id' => $newBill->id]);
            } else {
                $cloneId = $this->cloneOrderForSplit((int) $orderId, $newBill->id);
                OrderItem::whereIn('id', $itemsForOrder->pluck('id'))->update(['order_id' => $cloneId]);
            }
        }

        return $newBill;
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    private function sumLines(Collection $items): float
    {
        return round($items->sum(fn (OrderItem $i) => (float) $i->unit_price * (int) $i->quantity), 2);
    }

    private function cloneOrderForSplit(int $sourceOrderId, int $newBillId): int
    {
        $source = Order::findOrFail($sourceOrderId);
        $clone = $source->replicate();
        $clone->bill_id = $newBillId;
        $clone->save();

        return $clone->id;
    }
}
