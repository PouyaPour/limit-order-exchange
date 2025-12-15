<?php

namespace App\Jobs;

use App\Enums\OrderSideEnum;
use App\Models\Trade;
use App\Models\User;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTradeNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function backoff(): array
    {
        return [10, 50, 100];
    }


    public function __construct(public readonly Trade $trade, public readonly int $userId)
    {
        $this->onQueue('notifications');
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        try {
            $user = User::find($this->userId);

            if (!$user) {
                Log::warning('User not found for trade notification', [
                    'user_id' => $this->userId,
                    'trade_id' => $this->trade->id,
                ]);
                return;
            }

            $this->trade->load(['buyOrder.user']);

            $isBuyer = $this->userId === $this->trade->buyOrder->user_id;
            $side = $isBuyer ? OrderSideEnum::BUY->value : OrderSideEnum::SELL->value;

            Log::info('Sending trade notification', [
                'user_id' => $user->id,
                'trade_id' => $this->trade->id,
                'side' => $side,
            ]);

            Log::info('Trade notification sent', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'trade_id' => $this->trade->id,
                'symbol' => $this->trade->symbol,
                'amount' => $this->trade->amount,
                'price' => $this->trade->price,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send trade notification', [
                'user_id' => $this->userId,
                'trade_id' => $this->trade->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Trade notification job failed permanently', [
            'user_id' => $this->userId,
            'trade_id' => $this->trade->id,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'notifications',
            'trade:' . $this->trade->id,
            'user:' . $this->userId,
        ];
    }
}
