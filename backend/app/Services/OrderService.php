<?php

namespace App\Services;

use App\Enums\OrderSideEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\SymbolEnum;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Jobs\ProcessOrderMatching;
use App\Models\Order;
use App\Models\User;
use App\Models\Asset;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class OrderService
{
    public function __construct(private readonly AssetService $assetService)
    {
    }

    /**
     * @throws Throwable
     */
    public function createOrder(
        User $user,
        SymbolEnum $symbol,
        OrderSideEnum $side,
        string $price,
        string $amount
    ): Order {
        return DB::transaction(function () use ($user, $symbol, $side, $price, $amount) {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($side->isBuy()) {
                return $this->createBuyOrder($user, $symbol, $price, $amount);
            } else {
                return $this->createSellOrder($user, $symbol, $price, $amount);
            }
        });
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    private function createBuyOrder(
        User $user,
        SymbolEnum $symbol,
        string $price,
        string $amount
    ): Order {
        $totalValue = bcmul($price, $amount, 8);
        $commission = bcmul($totalValue, config('order.commission_rate'), 8);
        $requiredBalance = bcadd($totalValue, $commission, 8);

        return DB::transaction(function () use ($user, $symbol, $price, $amount, $requiredBalance) {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (!$user->hasAvailableBalance($requiredBalance)) {
                throw new Exception('Insufficient balance');
            }

            $user->balance = bcsub($user->balance, $requiredBalance, 8);
            $user->save();

            $order =  Order::create([
                'user_id' => $user->id,
                'symbol' => $symbol,
                'side' => OrderSideEnum::BUY,
                'price' => $price,
                'amount' => $amount,
                'status' => OrderStatusEnum::OPEN,
                'locked_balance' => $requiredBalance,
            ]);

            Log::info('Buy order created', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'symbol' => $symbol->value,
                'price' => $price,
                'amount' => $amount,
                'locked_balance' => $requiredBalance,
            ]);

            event(new OrderCreated($order));

            return $order;
        });
    }

    /**
     * @throws Throwable
     */
    private function createSellOrder(
        User $user,
        SymbolEnum $symbol,
        string $price,
        string $amount
    ): Order {
        return DB::transaction(function () use ($user, $symbol, $price, $amount) {
            $asset = Asset::lockForUpdate()
                ->where('user_id', $user->id)
                ->where('symbol', $symbol)
                ->first();

            if (!$asset || !$this->assetService->hasAvailableAmount($asset, $amount)) {
                throw new Exception('Insufficient asset amount');
            }

            $this->assetService->lockAmount($asset, $amount);

           $order = Order::create([
                'user_id' => $user->id,
                'symbol' => $symbol,
                'side' => OrderSideEnum::SELL,
                'price' => $price,
                'amount' => $amount,
                'status' => OrderStatusEnum::OPEN,
                'locked_balance' => $amount,
            ]);

            Log::info('Sell order created', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'symbol' => $symbol,
                'price' => $price,
                'amount' => $amount,
            ]);

            event(new OrderCreated($order));
            return $order;
        });
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function cancelOrder(Order $order, User $user): Order
    {
        if ($order->user_id !== $user->id) {
            throw new Exception('Unauthorized to cancel this order');
        }

        if (!$order->isOpen()) {
            throw new Exception('Order cannot be cancelled');
        }

        return DB::transaction(function () use ($order, $user) {
            $order = Order::lockForUpdate()->find($order->id);
            $user = User::lockForUpdate()->find($user->id);

            if (!$order->isOpen()) {
                throw new Exception('Order is no longer open');
            }

            if ($order->isBuy()) {
                $user->balance = bcadd($user->balance, $order->locked_balance, 8);
                $user->save();
            } else {
                $asset = Asset::lockForUpdate()
                    ->where('user_id', $user->id)
                    ->where('symbol', $order->symbol)
                    ->first();

                $this->assetService->unlockAmount($asset, $order->locked_balance);
                $asset->save();
            }

            $this->markAsCancelled($order);

            event(new OrderCancelled($order));

            Log::info('Order cancelled', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'side' => $order->side,
                'locked_balance' => $order->locked_balance,
            ]);

            return $order;
        });
    }

    public function getOrderbook(SymbolEnum $symbol): array
    {
        $buyOrders = Order::query()
            ->where('symbol', $symbol)
            ->where('side', OrderSideEnum::BUY)
            ->where('status', OrderStatusEnum::OPEN)
            ->orderBy('price', 'desc')
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        $sellOrders = Order::query()
            ->where('symbol', $symbol)
            ->where('side', OrderSideEnum::SELL)
            ->where('status', OrderStatusEnum::OPEN)
            ->orderBy('price')
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        return [
            'buy_orders' => $buyOrders,
            'sell_orders' => $sellOrders,
        ];
    }

    public function getUserOrders(
        User $user,
        ?SymbolEnum $symbol = null,
        ?OrderStatusEnum $status = null
    ): Collection {
        $query = Order::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($symbol) {
            $query->where('symbol', $symbol);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function processMatching(Order $order): void
    {
        $orderValue = bcmul($order->price, $order->amount, 8);

        $queue = bccomp($orderValue, '10000', 8) > 0
            ? 'high'
            : 'default';

        ProcessOrderMatching::dispatch($order)
            ->onQueue($queue)
            ->delay(10);

        Log::info('Order matching job dispatched', [
            'order_id' => $order->id,
            'queue' => $queue,
        ]);
    }

    public function markAsFilled(Order $order): void
    {
        $order->status = OrderStatusEnum::FILLED;
        $order->save();
    }
    public function markAsCancelled(Order $order): void
    {
        $order->status = OrderStatusEnum::CANCELLED;
        $order->save();
    }
}
