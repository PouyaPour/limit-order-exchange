<?php

namespace App\Listeners;

use App\Events\OrderMatched;
use App\Events\BalanceUpdated;
use App\Http\Resources\Api\V1\Profile\UserResource;
use App\Models\User;
use App\Services\AssetService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdateUserBalanceAfterMatch implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly AssetService $assetService)
    {
    }

    public function handle(OrderMatched $event): void
    {
        $trade = $event->trade;
        $userId = $event->userId;

        try {
            $user = User::with('assets')->findOrFail($userId);

            if (!$user) {
                Log::error('User not found for balance update', ['user_id' => $userId]);
                return;
            }

            $balances = UserResource::make($user);

            broadcast(new BalanceUpdated($userId, $balances));

            Log::info('Balance updated after match', [
                'user_id' => $userId,
                'trade_id' => $trade->id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update balance after match', [
                'user_id' => $userId,
                'trade_id' => $trade->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
