<?php

namespace App\Http\Resources\Api\V1\Profile;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class BalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'usd' => $this->balance,
        ];
    }
}
