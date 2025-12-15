<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\MatchingService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessOrderMatching implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly Order $order)
    {
    }

    public function backoff(): array
    {
        return [10, 50, 100];
    }

    /**
     * @throws Throwable
     */
    public function handle(MatchingService $matchingService): void
    {
        try {
            $order = Order::find($this->order->id);

            if (!$order) {
                Log::warning('Order not found for matching', [
                    'order_id' => $this->order->id,
                ]);
                return;
            }

            if (!$order->isOpen()) {
                Log::info('Order is no longer open, skipping matching', [
                    'order_id' => $order->id,
                    'status' => $order->status->value,
                ]);
                return;
            }

            Log::info('Processing order matching', [
                'order_id' => $order->id,
                'symbol' => $order->symbol,
                'side' => $order->side,
                'price' => $order->price,
                'amount' => $order->amount,
            ]);

            $trade = $matchingService->matchOrder($order);

            if ($trade) {
                Log::info('Order matched successfully', [
                    'order_id' => $order->id,
                    'trade_id' => $trade->id,
                    'match_price' => $trade->price,
                    'match_amount' => $trade->amount,
                ]);
            } else {
                Log::info('No matching order found', [
                    'order_id' => $order->id,
                    'symbol' => $order->symbol,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Failed to process order matching', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Order matching job failed permanently', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'order-matching',
            'order:' . $this->order->id,
            'symbol:' . $this->order->symbol,
        ];
    }
}
