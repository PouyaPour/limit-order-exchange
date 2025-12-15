<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->order->user_id),
            new Channel('orderbook.'.$this->order->symbol),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'symbol' => $this->order->symbol->value,
                'side' => $this->order->side->value,
                'price' => $this->order->price,
                'amount' => $this->order->amount,
                'status' => $this->order->status->value,
                'created_at' => $this->order->created_at->toISOString(),
            ],
            'message' => "Your {$this->order->side} order for {$this->order->amount} {$this->order->symbol} has been cancelled",
            'timestamp' => now()->toISOString(),
        ];
    }
}
