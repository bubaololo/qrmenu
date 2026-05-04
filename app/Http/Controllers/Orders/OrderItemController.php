<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\UpdateKitchenStatusAction;
use App\Enums\OrderItemKitchenStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\UpdateOrderItemKitchenStatusRequest;
use App\Http\Resources\Orders\OrderItemResource;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Gate;

class OrderItemController extends Controller
{
    public function update(
        UpdateOrderItemKitchenStatusRequest $request,
        OrderItem $orderItem,
        UpdateKitchenStatusAction $updateKitchen,
    ): OrderItemResource {
        Gate::authorize('update', $orderItem->order);

        $newStatus = OrderItemKitchenStatus::from($request->validated('kitchen_status'));
        $updated = $updateKitchen($orderItem, $newStatus);

        return new OrderItemResource($updated);
    }
}
