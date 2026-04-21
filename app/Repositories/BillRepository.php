<?php

namespace App\Repositories;

use App\Enums\BillPaymentStatus;
use App\Models\Bill;
use Illuminate\Database\Eloquent\Collection;

class BillRepository
{
    public function getAll(): Collection
    {
        return Bill::with(['shop', 'creator'])->latest()->get();
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
