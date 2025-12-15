<?php

namespace App\Events;

use App\Enums\OrderSideEnum;
use App\Models\Trade;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Trade $trade, public readonly  int $userId)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    public function broadcastWith(): array
    {
        $this->trade->load(['buyOrder.user', 'sellOrder.user']);

        $isBuyer = $this->userId === $this->trade->buyOrder->user_id;

        return [
            'trade' => [
                'id' => $this->trade->id,
                'symbol' => $this->trade->symbol->value,
                'price' => $this->trade->price,
                'amount' => $this->trade->amount,
                'total_value' => $this->trade->total_value,
                'commission' => $this->trade->commission,
                'executed_at' => $this->trade->executed_at->toISOString(),

                'user_side' => $isBuyer ? OrderSideEnum::BUY->value : OrderSideEnum::SELL->value,

                'user_order_id' => $isBuyer ? $this->trade->buy_order_id : $this->trade->sell_order_id,
            ],

            'message' => $isBuyer
                ? "Your buy order for {$this->trade->amount} {$this->trade->symbol->value} has been filled at {$this->trade->price}"
                : "Your sell order for {$this->trade->amount} {$this->trade->symbol->value} has been filled at {$this->trade->price}",

            'timestamp' => now()->toISOString(),
        ];
    }
}
