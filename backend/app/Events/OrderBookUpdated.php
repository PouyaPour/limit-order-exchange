<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderBookUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public function __construct(public readonly string $symbol, public readonly array $orderBook)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('orderbook.' . $this->symbol),
        ];
    }

    public function broadcastAs(): string
    {
        return 'orderbook.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'symbol' => $this->symbol,
            'orderbook' => $this->orderBook,
            'timestamp' => now()->toISOString(),
        ];
    }
}
