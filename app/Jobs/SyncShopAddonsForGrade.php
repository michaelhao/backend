<?php

namespace App\Jobs;

use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use App\Models\Grade;
use App\Models\Shop;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SyncShopAddonsForGrade implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Grade $grade)
    {
        $this->onQueue('addons');
    }

    public function handle(): void
    {
        $sNew = DB::table('grades_addons')
            ->where('grade_id', $this->grade->id)
            ->pluck('addon_id')
            ->all();

        $shopIds = Shop::where('grade_id', $this->grade->id)
            ->pluck('id')
            ->all();

        if (empty($shopIds)) {
            return;
        }

        $existingRows = DB::table('shops_addons')
            ->whereIn('shop_id', $shopIds)
            ->where('source', ShopAddonSource::Grade->value)
            ->get(['shop_id', 'addon_id']);

        $existingByShop = $existingRows
            ->groupBy('shop_id')
            ->map(fn ($rows) => $rows->pluck('addon_id')->all());

        $now = now()->format('Y-m-d H:i:s');
        $eod = now()->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        foreach ($shopIds as $shopId) {
            $sOld = $existingByShop[$shopId] ?? [];

            $toRemove = array_values(array_diff($sOld, $sNew));
            $toAdd = array_values(array_diff($sNew, $sOld));

            if (! empty($toRemove)) {
                DB::table('shops_addons')
                    ->where('shop_id', $shopId)
                    ->whereIn('addon_id', $toRemove)
                    ->where('source', ShopAddonSource::Grade->value)
                    ->update([
                        'source' => ShopAddonSource::Purchased->value,
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
                            'source' => ShopAddonSource::Grade->value,
                            'expired_at' => null,
                            'updated_at' => $now,
                        ]);
                }

                $pureNewIds = array_values(array_diff($toAdd, $existingPurchased));
                if (! empty($pureNewIds)) {
                    DB::table('shops_addons')->insertOrIgnore(
                        array_map(fn ($addonId) => [
                            'shop_id' => $shopId,
                            'addon_id' => $addonId,
                            'source' => ShopAddonSource::Grade->value,
                            'status' => ShopAddonStatus::Enabled->value,
                            'expired_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ], $pureNewIds)
                    );
                }
            }
        }
    }
}
