<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CloseBillAction;
use App\Actions\Orders\SplitBillAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\SplitBillRequest;
use App\Http\Resources\Orders\BillResource;
use App\Models\Bill;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BillController extends Controller
{
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Bill::class, $restaurant]);

        $query = Bill::query()
            ->whereHas('diningTable.zone', fn ($q) => $q->where('restaurant_id', $restaurant->id))
            ->with(['diningTable', 'orders.items'])
            ->orderByDesc('opened_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($tableId = $request->query('dining_table_id')) {
            $query->where('dining_table_id', $tableId);
        }

        return BillResource::collection($query->paginate(50));
    }

    public function show(Bill $bill): BillResource
    {
        Gate::authorize('view', $bill);

        return new BillResource($bill->load(['diningTable', 'orders.items']));
    }

    public function close(Request $request, Bill $bill, CloseBillAction $closeBill): BillResource
    {
        Gate::authorize('update', $bill);

        return new BillResource($closeBill($bill, $request->user()));
    }

    public function split(SplitBillRequest $request, Bill $bill, SplitBillAction $splitBill): AnonymousResourceCollection
    {
        Gate::authorize('update', $bill);

        $bills = $splitBill($bill, $request->validated('splits'), $request->user());

        return BillResource::collection($bills);
    }
}
