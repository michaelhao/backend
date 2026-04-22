<?php

namespace App\Repositories;

use App\Enums\BillPaymentStatus;
use App\Models\Bill;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BillRepository
{
    public function getAll(): Collection
    {
        return Bill::with(['shop', 'creator'])->latest()->get();
    }

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

    public function getPendingOrUnpaidCountForShop(int $shopId): int
    {
        return Bill::where('shop_id', $shopId)
            ->whereIn('payment_status', [BillPaymentStatus::Pending->value, BillPaymentStatus::Unpaid->value])
            ->count();
    }
}
