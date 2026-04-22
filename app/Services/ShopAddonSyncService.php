<?php

namespace App\Services;

use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use Illuminate\Support\Facades\DB;

class ShopAddonSyncService
{
    /**
     * Sync shops_addons (source=Grade) for a single shop against a new addon set.
     *
     * @param  int[]  $newAddonIds  Addon IDs the shop should hold via its grade.
     */
    public function syncForShop(int $shopId, array $newAddonIds): void
    {
        DB::transaction(function () use ($shopId, $newAddonIds) {
            $now = now()->format('Y-m-d H:i:s');
            $eod = now()->setTime(23, 59, 59)->format('Y-m-d H:i:s');

            $currentAddonIds = DB::table('shops_addons')
                ->where('shop_id', $shopId)
                ->where('source', ShopAddonSource::Grade->value)
                ->pluck('addon_id')
                ->all();

            $toRemove = array_values(array_diff($currentAddonIds, $newAddonIds));
            $toAdd    = array_values(array_diff($newAddonIds, $currentAddonIds));

            if (! empty($toRemove)) {
                DB::table('shops_addons')
                    ->where('shop_id', $shopId)
                    ->whereIn('addon_id', $toRemove)
                    ->where('source', ShopAddonSource::Grade->value)
                    ->update([
                        'source'     => ShopAddonSource::Purchased->value,
                        'expired_at' => $eod,
                        'updated_at' => $now,
                    ]);
            }

            if (! empty($toAdd)) {
                $existingPurchased = DB::table('shops_addons')
                    ->where('shop_id', $shopId)
                    ->whereIn('addon_id', $toAdd)
                    ->where('source', ShopAddonSource::Purchased->value)
                    ->pluck('addon_id')
                    ->all();

                if (! empty($existingPurchased)) {
                    DB::table('shops_addons')
                        ->where('shop_id', $shopId)
                        ->whereIn('addon_id', $existingPurchased)
                        ->update([
                            'source'     => ShopAddonSource::Grade->value,
                            'expired_at' => null,
                            'updated_at' => $now,
                        ]);
                }

                $pureNewIds = array_values(array_diff($toAdd, $existingPurchased));
                if (! empty($pureNewIds)) {
                    DB::table('shops_addons')->insert(
                        array_map(fn ($addonId) => [
                            'shop_id'    => $shopId,
                            'addon_id'   => $addonId,
                            'source'     => ShopAddonSource::Grade->value,
                            'status'     => ShopAddonStatus::Enabled->value,
                            'expired_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ], $pureNewIds)
                    );
                }
            }
        });
    }
}
