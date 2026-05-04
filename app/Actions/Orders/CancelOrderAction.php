<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\AnalysisEventBroker;
use Illuminate\Validation\ValidationException;

class CancelOrderAction
{
    public function __construct(private readonly AnalysisEventBroker $broker) {}

    public function __invoke(Order $order, string $reason = 'Removed by staff'): Order
    {
        if ($order->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'Order is already in a terminal state.',
            ]);
        }

        $order->update([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
        ]);

        $order->loadMissing('bill.diningTable.zone');
        $restaurantId = $order->bill?->diningTable?->zone?->restaurant_id;

        if ($restaurantId !== null) {
            $this->broker->publish(
                "restaurant-orders.{$restaurantId}",
                'order.status-changed',
                [
                    'order_id' => $order->id,
                    'status' => OrderStatus::Cancelled->value,
                ],
            );
        }

        return $order;
    }
}
