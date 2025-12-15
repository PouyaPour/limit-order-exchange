<?php

namespace App\Http\Resources\Api\V1\Order;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderbookItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price' => $this->price,
            'amount' => $this->amount,
            'total' => $this->getTotalValue(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
