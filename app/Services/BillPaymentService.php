<?php

namespace App\Services;

use App\Enums\AddonType;
use App\Enums\BillDetailType;
use App\Enums\BillPaymentStatus;
use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use App\Models\Addon;
use App\Models\Bill;
use App\Models\BillDetail;
use App\Models\BillStatusLog;
use App\Models\Grade;
use App\Models\Shop;
use App\Models\ShopAddon;
use App\Models\User;
use App\Repositories\BillFutureEffectRepository;
use App\Repositories\ShopAddonBalanceRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillPaymentService
{
    public function __construct(
        private BillFutureEffectRepository $futureEffectRepository,
        private ShopAddonBalanceRepository $shopAddonBalanceRepository,
        private ShopAddonSyncService $shopAddonSyncService,
    ) {}

    /**
     * Update bill fields (payment_status, paid_at, invoice_no).
     * If status transitions to Paid, triggers the install flow with distributed lock.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  429 if lock unavailable
     */
    public function update(Bill $bill, array $data, User $operator): void
    {
        $newStatus = isset($data['payment_status']) ? BillPaymentStatus::from((int) $data['payment_status']) : null;
        $transitionToPaid = $newStatus === BillPaymentStatus::Paid && $bill->payment_status !== BillPaymentStatus::Paid;

        $lock = null;
        if ($transitionToPaid) {
            $lock = Cache::lock("bill_pay_{$bill->id}", 10);
            if (! $lock->get()) {
                abort(429, '付款處理中，請勿重複操作');
            }
        }

        try {
            DB::transaction(function () use ($bill, $data, $operator, $newStatus, $transitionToPaid) {
                $fromStatus = $bill->payment_status;

                $updates = [];
                if ($newStatus !== null) {
                    $updates['payment_status'] = $newStatus;
                }
                if (array_key_exists('paid_at', $data)) {
                    $updates['paid_at'] = $data['paid_at'];
                }
                if (array_key_exists('invoice_no', $data)) {
                    $updates['invoice_no'] = $data['invoice_no'];
                }

                $bill->update($updates);

                if ($newStatus !== null && $newStatus !== $fromStatus) {
                    BillStatusLog::create([
                        'bill_id'     => $bill->id,
                        'from_status' => $fromStatus->value,
                        'to_status'   => $newStatus->value,
                        'operator_id' => $operator->id,
                    ]);
                }

                if ($transitionToPaid) {
                    $this->installBillDetails($bill);
                }
            });
        } finally {
            $lock?->release();
        }
    }

    /**
     * Install a single bill detail onto the shop.
     * Idempotent: skips if applied_at is already set.
     */
    public function installDetail(BillDetail $detail): void
    {
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

    private function installBillDetails(Bill $bill): void
    {
        $today = today()->toDateString();

        foreach ($bill->details()->where('is_effective', 1)->where('type', '!=', BillDetailType::Discount->value)->get() as $detail) {
            // Pre-load the bill relation to avoid a lazy-load inside installDetail
            $detail->setRelation('bill', $bill);

            if ($detail->start_at->toDateString() <= $today) {
                $this->installDetail($detail);
            } else {
                $this->futureEffectRepository->createFromDetail($detail);
            }
        }
    }

    private function installGradeDetail(Shop $shop, BillDetail $detail): void
    {
        if (! $detail->grade_id) {
            Log::warning("BillPaymentService: grade_id missing for detail #{$detail->id}");

            return;
        }

        $newGrade = Grade::find($detail->grade_id);

        if (! $newGrade) {
            Log::warning("BillPaymentService: grade #{$detail->grade_id} not found for detail #{$detail->id}");

            return;
        }

        $shop->update([
            'grade_id' => $newGrade->id,
            'expired_at' => $detail->expired_at,
        ]);

        $newAddonIds = $newGrade->addons()->pluck('addons.id')->toArray();
        $this->shopAddonSyncService->syncForShop($shop->id, $newAddonIds);
    }

    private function installAddonDetail(Shop $shop, BillDetail $detail): void
    {
        if (! $detail->addon_id) {
            Log::warning("BillPaymentService: addon_id missing for detail #{$detail->id}");

            return;
        }

        $addon = Addon::find($detail->addon_id);

        if (! $addon) {
            Log::warning("BillPaymentService: addon #{$detail->addon_id} not found for detail #{$detail->id}");

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
            $this->shopAddonBalanceRepository->create(
                $shop->id,
                $addon->id,
                $detail->quantity,
                $detail->expired_at,
            );
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
                // Eager-load detail.bill so installDetail doesn't trigger a lazy load
                $effect->load('detail.bill');
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
