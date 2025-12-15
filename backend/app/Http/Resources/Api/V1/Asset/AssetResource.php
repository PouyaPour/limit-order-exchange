<?php

namespace App\Http\Resources\Api\V1\Asset;

use App\Http\Resources\Api\V1\Profile\ProfileResource;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Asset */
class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'symbol' => $this->symbol->value,
            'amount' => $this->amount,
            'locked_amount' => $this->locked_amount,
            'total_amount' => $this->getTotalAmount()
        ];
    }
}
