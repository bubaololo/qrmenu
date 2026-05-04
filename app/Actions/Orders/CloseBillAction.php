<?php

namespace App\Actions\Orders;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\User;
use App\Services\AnalysisEventBroker;
use Illuminate\Validation\ValidationException;

class CloseBillAction
{
    public function __construct(private readonly AnalysisEventBroker $broker) {}

    public function __invoke(Bill $bill, ?User $closedBy = null): Bill
    {
        if ($bill->status === BillStatus::Closed) {
            throw ValidationException::withMessages(['status' => 'Bill is already closed.']);
        }

        $bill->load(['orders.items', 'diningTable.zone']);
        $total = $bill->recalculateTotal();

        $bill->update([
            'status' => BillStatus::Closed,
            'closed_at' => now(),
            'closed_by_user_id' => $closedBy?->id,
            'total_amount' => $total,
        ]);

        $restaurantId = $bill->diningTable?->zone?->restaurant_id;

        if ($restaurantId !== null) {
            $this->broker->publish(
                "restaurant-orders.{$restaurantId}",
                'bill.closed',
                [
                    'bill_id' => $bill->id,
                    'dining_table_id' => $bill->dining_table_id,
                    'total_amount' => $total,
                ],
            );
        }

        return $bill->fresh(['diningTable', 'orders.items']);
    }
}
