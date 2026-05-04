<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\TransitionOrderStatusAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Order::class, $restaurant]);

        $query = Order::query()
            ->forRestaurant($restaurant->id)
            ->with(['items', 'bill.diningTable'])
            ->orderByDesc('placed_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($tableId = $request->query('dining_table_id')) {
            $query->whereHas('bill', fn ($q) => $q->where('dining_table_id', $tableId));
        }

        if ($billId = $request->query('bill_id')) {
            $query->where('bill_id', $billId);
        }

        $perPage = (int) $request->query('per_page', 50);

        return OrderResource::collection($query->paginate(min($perPage, 200)));
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(['items', 'bill.diningTable']));
    }

    public function update(UpdateOrderRequest $request, Order $order, TransitionOrderStatusAction $transition): OrderResource
    {
        Gate::authorize('update', $order);

        $newStatus = OrderStatus::from($request->validated('status'));
        $updated = $transition($order, $newStatus, $request->validated('cancelled_reason'));

        return new OrderResource($updated);
    }

    public function destroy(Order $order, CancelOrderAction $cancel): JsonResponse
    {
        Gate::authorize('delete', $order);

        $cancel($order);

        return response()->json(null, 204);
    }
}
