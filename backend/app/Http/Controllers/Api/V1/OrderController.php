<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderSideEnum;
use App\Enums\SymbolEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\StoreRequest;
use App\Http\Resources\Api\V1\Order\OrderbookItemResource;
use App\Http\Resources\Api\V1\Order\OrderCancelledResource;
use App\Http\Resources\Api\V1\Order\OrderCreatedResource;
use App\Http\Resources\Api\V1\Order\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * @throws Throwable
     */
    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $order = $this->orderService->createOrder(
                $request->user(),
                SymbolEnum::from($validated['symbol']),
                OrderSideEnum::from($validated['side']),
                $validated['price'],
                $validated['amount']
            );

            $this->orderService->processMatching($order);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order' => new OrderCreatedResource($order),
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @throws Throwable
     */
    public function cancel(Order $order, Request $request): JsonResponse
    {
        try {
            if ($order->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            if (!$order->isOpen()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order cannot be cancelled',
                ], 400);
            }

            $order = $this->orderService->cancelOrder($order, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => [
                    'order' => new OrderCancelledResource($order),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = Order::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('symbol')) {
            $query->where('symbol', $request->input('symbol'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => OrderResource::collection($orders),
            ],
        ]);
    }

    public function orderbook(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'BTC');

        $orderbook = $this->orderService->getOrderbook(SymbolEnum::tryFrom($symbol));

        return response()->json([
            'success' => true,
            'data' => [
                'symbol' => $symbol,
                'buy_orders' => OrderbookItemResource::collection($orderbook['buy_orders']),
                'sell_orders' => OrderbookItemResource::collection($orderbook['sell_orders']),
            ],
        ]);
    }
}
