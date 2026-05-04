<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\AnalysisEventBroker;
use Illuminate\Validation\ValidationException;

class TransitionOrderStatusAction
{
    public function __construct(private readonly AnalysisEventBroker $broker) {}

    public function __invoke(Order $order, OrderStatus $newStatus, ?string $cancelledReason = null): Order
    {
        $oldStatus = $order->status;

        if (! $oldStatus->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$oldStatus->value} to {$newStatus->value}.",
            ]);
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === OrderStatus::InProgress && $order->started_at === null) {
            $updates['started_at'] = now();
        }
        if ($newStatus === OrderStatus::Completed) {
            $updates['completed_at'] = now();
        }
        if ($newStatus === OrderStatus::Cancelled) {
            $updates['cancelled_at'] = now();
            $updates['cancelled_reason'] = $cancelledReason;
        }

        $order->update($updates);

        $order->loadMissing('bill.diningTable.zone');
        $restaurantId = $order->bill?->diningTable?->zone?->restaurant_id;

        if ($restaurantId !== null) {
            $this->broker->publish(
                "restaurant-orders.{$restaurantId}",
                'order.status-changed',
                [
                    'order_id' => $order->id,
                    'status' => $newStatus->value,
                    'previous' => $oldStatus->value,
                ],
            );
        }

        return $order->fresh(['items', 'bill.diningTable']);
    }
}
