<?php

namespace App\Http\Controllers\Public;

use App\Actions\Orders\PlaceOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StorePublicOrderRequest;
use App\Http\Resources\Orders\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class PublicOrderController extends Controller
{
    private const COOKIE_NAME = 'guest_token';

    private const COOKIE_LIFETIME_MINUTES = 60 * 24 * 7;

    public function store(
        StorePublicOrderRequest $request,
        PlaceOrderAction $placeOrder,
    ): JsonResponse {
        $existingToken = $request->cookie(self::COOKIE_NAME);
        $order = $placeOrder($request->validated(), $existingToken);

        $response = (new OrderResource($order->load(['items', 'bill.diningTable'])))
            ->response()
            ->setStatusCode(201);

        if (! $existingToken || ! Str::isUuid($existingToken)) {
            $response->headers->setCookie(Cookie::create(
                name: self::COOKIE_NAME,
                value: $order->guest_token,
                expire: now()->addMinutes(self::COOKIE_LIFETIME_MINUTES)->getTimestamp(),
                path: '/',
                domain: null,
                secure: $request->isSecure(),
                httpOnly: true,
                raw: false,
                sameSite: Cookie::SAMESITE_LAX,
            ));
        }

        return $response;
    }

    public function show(Request $request, string $guestToken): AnonymousResourceCollection
    {
        $orders = Order::where('guest_token', $guestToken)
            ->with(['items', 'bill.diningTable'])
            ->orderByDesc('placed_at')
            ->limit(20)
            ->get();

        return OrderResource::collection($orders);
    }
}
