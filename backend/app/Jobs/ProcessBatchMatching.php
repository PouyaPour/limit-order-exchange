<?php

namespace App\Jobs;

use App\Enums\OrderStatusEnum;
use App\Enums\SymbolEnum;
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

class ProcessBatchMatching implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public readonly SymbolEnum $symbol, public readonly int $maxOrders = 50)
    {
        $this->onQueue('batch-matching');
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
        Log::info('Starting batch matching', [
            'symbol' => $this->symbol,
            'max_orders' => $this->maxOrders,
        ]);

        $matchedCount = 0;
        $attemptedCount = 0;

        try {
            $orders = Order::query()
                ->where('symbol', $this->symbol)
                ->where('status', OrderStatusEnum::OPEN)
                ->orderBy('created_at')
                ->limit($this->maxOrders)
                ->cursor();

            foreach ($orders as $order) {
                $attemptedCount++;

                $order->refresh();

                if (!$order->isOpen()) {
                    continue;
                }

                $trade = $matchingService->matchOrder($order);

                if ($trade) {
                    $matchedCount++;

                    Log::info('Batch matching success', [
                        'order_id' => $order->id,
                        'trade_id' => $trade->id,
                    ]);
                }
            }

            Log::info('Batch matching completed', [
                'symbol' => $this->symbol,
                'attempted' => $attemptedCount,
                'matched' => $matchedCount,
            ]);
        } catch (Exception $e) {
            Log::error('Batch matching failed', [
                'symbol' => $this->symbol,
                'attempted' => $attemptedCount,
                'matched' => $matchedCount,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Batch matching job failed permanently', [
            'symbol' => $this->symbol->value,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'batch-matching',
            'symbol:' . $this->symbol->value,
        ];
    }
}
