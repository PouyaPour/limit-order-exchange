<?php

namespace App\Http\Resources\Api\V1\Profile;

use App\Http\Resources\Api\V1\Asset\AssetResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => new ProfileResource($this),
            'balance' => new BalanceResource($this),
            'assets' => AssetResource::collection($this->whenLoaded('assets')),
        ];
    }
}
