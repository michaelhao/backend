<?php

namespace App\Repositories;

use App\Enums\BillDetailType;
use App\Models\Bill;
use App\Models\BillDetail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BillDetailRepository
{
    public function createBillDetails(Bill $bill, array $details): Collection
    {
        $created = new Collection();
        foreach ($details as $detail) {
            $created->push($bill->details()->create($detail));
        }

        return $created;
    }

    public function findByBillId(int $billId): Collection
    {
        return BillDetail::where('bill_id', $billId)->get();
    }

    public function getEffectiveByBill(int $billId): Collection
    {
        return BillDetail::where('bill_id', $billId)
            ->where('is_effective', 1)
            ->where('type', '!=', BillDetailType::Discount->value)
            ->get();
    }

    /**
     * @param  int[]  $ids
     */
    public function writeoff(array $ids, int $canceledBy): void
    {
        DB::table('bills_details')
            ->whereIn('id', $ids)
            ->update([
                'is_effective' => 0,
                'canceled_at' => now()->format('Y-m-d H:i:s'),
                'canceled_by' => $canceledBy,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);
    }
}
