<?php

namespace App\Repositories;

use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use App\Models\ShopAddon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class ShopAddonRepository
{
    public function getEnabledWithAddonForShop(int $shopId): Collection
    {
        return ShopAddon::with('addon')
            ->where('shop_id', $shopId)
            ->where('status', 1)
            ->get();
    }

    public function upsertPurchased(int $shopId, int $addonId, ?CarbonInterface $expiredAt): ShopAddon
    {
        return ShopAddon::updateOrCreate(
            ['shop_id' => $shopId, 'addon_id' => $addonId],
            [
                'source' => ShopAddonSource::Purchased,
                'status' => ShopAddonStatus::Enabled,
                'expired_at' => $expiredAt,
            ]
        );
    }
}
