<?php

namespace App\Listeners;

use App\Events\OrderMatched;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogTradeExecution implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderMatched $event): void
    {
        $trade = $event->trade;

        Log::info('Trade executed', [
            'trade_id' => $trade->id,
            'symbol' => $trade->symbol,
            'buyer_id' => $trade->buyOrder->user_id,
            'seller_id' => $trade->sellOrder->user_id,
            'price' => $trade->price,
            'amount' => $trade->amount,
            'total_value' => $trade->total_value,
            'commission' => $trade->commission,
            'executed_at' => $trade->executed_at->toISOString(),
        ]);
    }
}
