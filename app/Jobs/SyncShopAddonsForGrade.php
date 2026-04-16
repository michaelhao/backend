<?php

namespace App\Jobs;

use App\Models\Grade;
use App\Models\Shop;
use App\Services\ShopAddonSyncService;
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
        $this->onQueue('addon_sync');
    }

    public function handle(ShopAddonSyncService $sync): void
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

        foreach ($shopIds as $shopId) {
            $sync->syncForShop($shopId, $sNew);
        }
    }
}
