<?php

namespace App\Repositories;

use App\Models\ShopAddonBalance;
use Carbon\Carbon;

class ShopAddonBalanceRepository
{
    public function create(int $shopId, int $addonId, int $quantity, Carbon $expiredAt): ShopAddonBalance
    {
        return ShopAddonBalance::create([
            'shop_id' => $shopId,
            'addon_id' => $addonId,
            'quantity' => $quantity,
            'expired_at' => $expiredAt,
        ]);
    }

    public function getAvailableQuantity(int $shopId, int $addonId): int
    {
        return (int) ShopAddonBalance::query()
            ->where('shop_id', $shopId)
            ->where('addon_id', $addonId)
            ->where('expired_at', '>', now())
            ->sum('quantity');
    }
}
