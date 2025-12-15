<?php

namespace App\Services;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Models\Asset;
use App\Events\OrderMatched;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class MatchingService
{
    public function __construct(
        private AssetService $assetService,
        private OrderService $orderService,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function matchOrder(Order $newOrder): ?Trade
    {
        if (!$newOrder->isOpen()) {
            return null;
        }

        $counterOrder = $this->findBestMatch($newOrder);

        if (!$counterOrder) {
            return null;
        }

        return $this->executeMatch($newOrder, $counterOrder);
    }

    /**
     * Find the best counter order to match with
     */
    private function findBestMatch(Order $order): ?Order
    {
        $query = Order::query()
            ->where('symbol', $order->symbol)
            ->where('side', $order->side->opposite())
            ->where('status', OrderStatusEnum::OPEN)
            ->where('user_id', '!=', $order->user_id);


        if ($order->isBuy()) {
            $query->where('price', '<=', $order->price)
                ->orderBy('price')
                ->orderBy('created_at');
        } else {
            $query->where('price', '>=', $order->price)
                ->orderBy('price', 'desc')
                ->orderBy('created_at');
        }

        return $query->lockForUpdate()->first();
    }

    /**
     * @throws Throwable
     */
    private function executeMatch(Order $newOrder, Order $counterOrder): Trade
    {
        return DB::transaction(function () use ($newOrder, $counterOrder): Trade {
            $newOrder = Order::whereKey($newOrder->id)->lockForUpdate()->firstOrFail();
            $counterOrder = Order::whereKey($counterOrder->id)->lockForUpdate()->firstOrFail();


            if (!$newOrder->isOpen() || !$counterOrder->isOpen()) {
                throw new Exception('One or both orders are no longer open');
            }

            $buyOrder = $newOrder->isBuy() ? $newOrder : $counterOrder;
            $sellOrder = $newOrder->isSell() ? $newOrder : $counterOrder;

            $matchPrice = $counterOrder->price;
            $matchAmount = $newOrder->amount;

            $totalValue = bcmul($matchPrice, $matchAmount, 8);
            $commission = bcmul($totalValue, config('order.commission_rate'), 8);

            $this->transferAssets($buyOrder, $sellOrder, $matchAmount, $totalValue, $commission);


            $this->orderService->markAsFilled($buyOrder);
            $this->orderService->markAsFilled($sellOrder);

            $trade = Trade::create([
                'buy_order_id' => $buyOrder->id,
                'sell_order_id' => $sellOrder->id,
                'symbol' => $newOrder->symbol->value,
                'price' => $matchPrice,
                'amount' => $matchAmount,
                'total_value' => $totalValue,
                'commission' => $commission,
                'executed_at' => now(),
            ]);

            broadcast(new OrderMatched($trade, $buyOrder->user_id))->toOthers();
            broadcast(new OrderMatched($trade, $sellOrder->user_id))->toOthers();

            Log::info('Order matched', [
                'trade_id' => $trade->id,
                'symbol' => $trade->symbol->value,
                'price' => $trade->price,
                'amount' => $trade->amount,
                'buyer_id' => $buyOrder->user_id,
                'seller_id' => $sellOrder->user_id,
            ]);

            return $trade;
        });
    }

    /**
     * @throws Exception
     */
    private function transferAssets(
        Order $buyOrder,
        Order $sellOrder,
        string $amount,
        string $totalValue,
        string $commission
    ): void {
        $buyer = User::whereKey($buyOrder->user_id)->lockForUpdate()->firstOrFail();
        $seller = User::whereKey($sellOrder->user_id)->lockForUpdate()->firstOrFail();

        $requiredBalance = bcadd($totalValue, $commission, 8);

        if (bccomp($buyOrder->locked_balance, $requiredBalance, 8) < 0) {
            throw new Exception('Insufficient locked balance for buyer');
        }

        $sellerReceives = bcsub($totalValue, $commission, 8);
        $seller->balance = bcadd($seller->balance, $sellerReceives, 8);
        $seller->save();

        $sellerAsset = Asset::where('user_id', $seller->id)
            ->where('symbol', $buyOrder->symbol)
            ->lockForUpdate()
            ->first();

        if (!$sellerAsset) {
            throw new Exception('Seller asset not found');
        }

        $this->assetService->deductLockedAmount($sellerAsset, $amount);

        $buyerAsset = Asset::firstOrCreate(
            [
                'user_id' => $buyer->id,
                'symbol' => $buyOrder->symbol->value,
            ],
            [
                'amount' => '0.00000000',
                'locked_amount' => '0.00000000',
            ]
        );

        $this->assetService->addAmount($buyerAsset, $amount);

        Log::info('Assets transferred', [
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'symbol' => $buyOrder->symbol->value,
            'amount' => $amount,
            'total_value' => $totalValue,
            'commission' => $commission,
        ]);
    }
}
