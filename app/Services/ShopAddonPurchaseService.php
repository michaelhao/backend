<?php

namespace App\Services;

use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use App\Models\ShopAddon;
use App\Repositories\ShopAddonBalanceRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShopAddonPurchaseService
{
    public function __construct(
        private readonly ShopAddonBalanceRepository $balanceRepository
    ) {}

    public function purchase(int $shopId, int $addonId, int $quantity, Carbon $expiredAt): void
    {
        DB::transaction(function () use ($shopId, $addonId, $quantity, $expiredAt) {
            ShopAddon::updateOrCreate(
                ['shop_id' => $shopId, 'addon_id' => $addonId],
                [
                    'expired_at' => $expiredAt,
                    'source' => ShopAddonSource::Purchased,
                    'status' => ShopAddonStatus::Enabled,
                ]
            );

            $this->balanceRepository->create($shopId, $addonId, $quantity, $expiredAt);
        });
    }
}
