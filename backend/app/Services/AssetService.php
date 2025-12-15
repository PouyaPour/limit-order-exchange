<?php

namespace App\Services;

use App\Models\Asset;
use Exception;

class AssetService
{
    public function addAmount(Asset $asset, string $amount): void
    {
        $asset->amount = bcadd($asset->amount, $amount, 8);
        $asset->save();
    }

    public function hasAvailableAmount(Asset $asset, string $amount): bool
    {
        return bccomp($asset->amount, $amount, 8) >= 0;
    }

    /**
     * @throws Exception
     */
    public function lockAmount(Asset $asset, string $amount): void
    {
        $asset->amount = bcsub($asset->amount, $amount, 8);
        $asset->locked_amount = bcadd($asset->locked_amount, $amount, 8);
        $asset->save();
    }

    /**
     * @throws Exception
     */
    public function unlockAmount(Asset $asset, string $amount): void
    {
        if (bccomp($asset->locked_amount, $amount, 8) < 0) {
            throw new Exception('Insufficient locked amount');
        }

        $asset->locked_amount = bcsub($asset->locked_amount, $amount, 8);
        $asset->amount = bcadd($asset->amount, $amount, 8);
        $asset->save();
    }

    /**
     * @throws Exception
     */
    public function deductLockedAmount(Asset $asset, string $amount): void
    {
        if (bccomp($asset->locked_amount, $amount, 8) < 0) {
            throw new Exception('Insufficient locked amount');
        }

        $asset->locked_amount = bcsub($asset->locked_amount, $amount, 8);
        $asset->save();
    }
}
