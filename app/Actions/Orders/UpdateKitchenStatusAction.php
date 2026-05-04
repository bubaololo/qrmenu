<?php

namespace App\Actions\Orders;

use App\Enums\OrderItemKitchenStatus;
use App\Models\OrderItem;
use App\Services\AnalysisEventBroker;

class UpdateKitchenStatusAction
{
    public function __construct(private readonly AnalysisEventBroker $broker) {}

    public function __invoke(OrderItem $orderItem, OrderItemKitchenStatus $newStatus): OrderItem
    {
        $updates = ['kitchen_status' => $newStatus];

        if ($newStatus === OrderItemKitchenStatus::Cooking && $orderItem->started_cooking_at === null) {
            $updates['started_cooking_at'] = now();
        }
        if ($newStatus === OrderItemKitchenStatus::Ready) {
            $updates['ready_at'] = now();
        }
        if ($newStatus === OrderItemKitchenStatus::Served) {
            $updates['served_at'] = now();
        }

        $orderItem->update($updates);

        $orderItem->loadMissing('order.bill.diningTable.zone');
        $restaurantId = $orderItem->order?->bill?->diningTable?->zone?->restaurant_id;

        if ($restaurantId !== null) {
            $this->broker->publish(
                "restaurant-orders.{$restaurantId}",
                'order-item.kitchen-status-changed',
                [
                    'order_item_id' => $orderItem->id,
                    'order_id' => $orderItem->order_id,
                    'kitchen_status' => $newStatus->value,
                ],
            );
        }

        return $orderItem->fresh();
    }
}
