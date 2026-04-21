<?php

namespace App\Services;

use App\Enums\AddonType;
use App\Enums\BillDetailType;
use App\Enums\BillPaymentStatus;
use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use App\Models\Bill;
use App\Models\BillDetail;
use App\Models\BillStatusLog;
use App\Models\Shop;
use App\Models\ShopAddon;
use App\Models\ShopAddonBalance;
use App\Models\User;
use App\Repositories\BillFutureEffectRepository;
use App\Repositories\BillRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillPaymentService
{
    public function __construct(
        private BillRepository $billRepository,
        private BillFutureEffectRepository $futureEffectRepository,
        private ShopAddonSyncService $shopAddonSyncService,
    ) {}

    /**
     * Process payment for a bill. Uses a distributed lock to prevent duplicate payments.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  429 if lock unavailable
     */
    public function pay(Bill $bill, User $operator): void
    {
        $lock = Cache::lock("bill_pay_{$bill->id}", 10);

        if (! $lock->get()) {
            abort(429, '付款處理中，請勿重複操作');
        }

        try {
            DB::transaction(function () use ($bill, $operator) {
                $fromStatus = $bill->payment_status;

                $this->billRepository->updateStatus($bill, BillPaymentStatus::Paid);
                $bill->update(['paid_at' => now()]);

                BillStatusLog::create([
                    'bill_id' => $bill->id,
                    'from_status' => $fromStatus->value,
                    'to_status' => BillPaymentStatus::Paid->value,
                    'operator_id' => $operator->id,
                ]);

                $today = today()->toDateString();

                foreach ($bill->details()->where('is_effective', 1)->where('type', '!=', BillDetailType::Discount->value)->get() as $detail) {
                    $detailDate = $detail->start_at->toDateString();

                    if ($detailDate === $today) {
                        $this->installDetail($detail);
                    } else {
                        $this->futureEffectRepository->createFromDetail($detail);
                    }
                }
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Install a single bill detail onto the shop.
     * Idempotent: skips if applied_at is already set.
     */
    public function installDetail(BillDetail $detail): void
    {
        // Idempotency guard
        if ($detail->applied_at !== null) {
            return;
        }

        DB::transaction(function () use ($detail) {
            $shop = Shop::lockForUpdate()->findOrFail($detail->bill->shop_id);

            if (in_array($detail->type->value, [BillDetailType::Grades->value, BillDetailType::UpgradeFeeDiff->value])) {
                $this->installGradeDetail($shop, $detail);
            } elseif ($detail->type->value === BillDetailType::Addons->value) {
                $this->installAddonDetail($shop, $detail);
            }

            $detail->update(['applied_at' => now()]);
        });
    }

    private function installGradeDetail(Shop $shop, BillDetail $detail): void
    {
        $grade = \App\Models\Grade::findOrFail($detail->bill->shop->grade_id);
        $newGrade = \App\Models\Grade::where('name', $detail->name)->first();

        if (! $newGrade) {
            Log::warning("BillPaymentService: grade not found for detail #{$detail->id}, name={$detail->name}");

            return;
        }

        $shop->update([
            'grade_id' => $newGrade->id,
            'expired_at' => $detail->expired_at,
        ]);

        // Sync grade-based addons
        $newAddonIds = $newGrade->addons()->pluck('addons.id')->toArray();
        $this->shopAddonSyncService->syncForShop($shop->id, $newAddonIds);
    }

    private function installAddonDetail(Shop $shop, BillDetail $detail): void
    {
        $addon = \App\Models\Addon::where('name', $detail->name)->first();

        if (! $addon) {
            Log::warning("BillPaymentService: addon not found for detail #{$detail->id}, name={$detail->name}");

            return;
        }

        ShopAddon::updateOrCreate(
            ['shop_id' => $shop->id, 'addon_id' => $addon->id],
            [
                'source' => ShopAddonSource::Purchased,
                'status' => ShopAddonStatus::Enabled,
                'expired_at' => $detail->expired_at,
            ]
        );

        if ($addon->type === AddonType::Quota) {
            ShopAddonBalance::create([
                'shop_id' => $shop->id,
                'addon_id' => $addon->id,
                'quantity' => $detail->quantity,
                'expired_at' => $detail->expired_at,
            ]);
        }
    }

    /**
     * Run all pending future effects up to today.
     * Called by the artisan command.
     */
    public function processFutureEffects(): void
    {
        $effects = $this->futureEffectRepository->getPendingUpToToday();

        foreach ($effects as $effect) {
            try {
                $effect->load('detail');
                $this->installDetail($effect->detail);
                $this->futureEffectRepository->markFinished($effect);
            } catch (\Throwable $e) {
                Log::error("ProcessFutureEffects: failed for effect #{$effect->id}: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }
    }
}
