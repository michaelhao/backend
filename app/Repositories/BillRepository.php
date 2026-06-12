<?php

namespace App\Repositories;

use App\Enums\BillPaymentStatus;
use App\Models\Bill;
use App\Models\BillStatusLog;
use Illuminate\Pagination\LengthAwarePaginator;

class BillRepository
{
    /**
     * @param  array{no?: string, payment_method?: string, payment_status?: string, sales_id?: string}  $filters
     */
    public function paginate(int $perPage, array $filters): LengthAwarePaginator
    {
        return Bill::query()
            ->with(['shop', 'shopSales'])
            ->when($filters['no'] ?? null, fn ($q, $no) => $q->where('no', 'like', "%{$no}%"))
            ->when($filters['payment_method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
            ->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($filters['sales_id'] ?? null, fn ($q, $v) => $q->where('shop_sales_id', $v))
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): ?Bill
    {
        return Bill::with(['shop', 'details', 'statusLogs.operator'])->find($id);
    }

    public function getById(int $id): ?Bill
    {
        return Bill::find($id);
    }

    public function getByIdWithShopCreatorDetails(int $id): ?Bill
    {
        return Bill::with(['shop', 'creator', 'details'])->find($id);
    }

    public function getByIdWithEffectiveDetails(int $id): ?Bill
    {
        return Bill::with(['shop', 'details' => fn ($q) => $q->where('is_effective', 1)])->find($id);
    }

    /**
     * @param  string[]  $nos
     * @return string[]
     */
    public function getTakenNos(array $nos): array
    {
        return Bill::whereIn('no', $nos)->pluck('no')->all();
    }

    public function createStatusLog(array $data): BillStatusLog
    {
        return BillStatusLog::create($data);
    }

    public function create(array $data): Bill
    {
        return Bill::create($data);
    }

    public function updateTotals(Bill $bill, int $total, int $totalGrade, int $totalAddons, ?int $discountAmount): void
    {
        $bill->update([
            'total' => $total,
            'total_grade' => $totalGrade,
            'total_addons' => $totalAddons,
            'discount_amount' => $discountAmount,
        ]);
    }

    public function updateStatus(Bill $bill, BillPaymentStatus $status): void
    {
        $bill->update(['payment_status' => $status]);
    }

    public function existsByShopSalesUserId(int $userId): bool
    {
        return Bill::where('shop_sales_id', $userId)->exists();
    }

    public function getPendingOrUnpaidCountForShop(int $shopId): int
    {
        return Bill::where('shop_id', $shopId)
            ->whereIn('payment_status', [BillPaymentStatus::Pending->value, BillPaymentStatus::Unpaid->value])
            ->count();
    }
}
