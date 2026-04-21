<?php

namespace App\Services;

use App\Enums\BillDetailType;
use App\Enums\BillPaymentStatus;
use App\Models\Addon;
use App\Models\Bill;
use App\Models\BillDiscount;
use App\Models\BillStatusLog;
use App\Models\Grade;
use App\Models\Shop;
use App\Models\User;
use App\Repositories\BillDetailRepository;
use App\Repositories\BillRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillService
{
    public function __construct(
        private BillRepository $billRepository,
        private BillDetailRepository $billDetailRepository,
        private BillCalculationService $calc,
    ) {}

    /**
     * @return array{bills: Collection}
     */
    public function getIndexData(): array
    {
        return [
            'bills' => $this->billRepository->getAll(),
        ];
    }

    /**
     * AJAX: search shops by id, code, or name keyword (max 10).
     */
    public function shopSearch(string $keyword): Collection
    {
        return Shop::with('grade')
            ->where(function ($q) use ($keyword) {
                if (is_numeric($keyword)) {
                    $q->where('id', (int) $keyword);
                } else {
                    $q->where('code', $keyword)
                        ->orWhere('name', 'like', "%{$keyword}%");
                }
            })
            ->limit(10)
            ->get();
    }

    /**
     * AJAX: validate shop exists and has sales_id.
     *
     * @return array{shop: Shop, pending_bill_count: int}
     *
     * @throws \InvalidArgumentException
     */
    public function shopInfo(int $shopId): array
    {
        $shop = Shop::with(['grade', 'sales'])->find($shopId);

        if (! $shop) {
            throw new \InvalidArgumentException('商店不存在');
        }

        if (! $shop->sales_id) {
            throw new \InvalidArgumentException('此商店尚未設定負責業務，無法建立帳單');
        }

        return [
            'shop' => $shop,
            'pending_bill_count' => $this->billRepository->getPendingOrUnpaidCountForShop($shopId),
            'grades' => Grade::orderBy('weight')->get(['id', 'name', 'price', 'weight']),
            'addons' => Addon::orderBy('name')->get(['id', 'name', 'price', 'type']),
            'shop_addons' => $shop->addons()->with('addon')->where('status', 1)->get(),
        ];
    }

    /**
     * AJAX: calculate amount and expired_at for one detail line.
     *
     * @return array{total_price: int, expired_at: string}
     */
    public function calculateDetail(array $params): array
    {
        $unitPrice = (int) $params['unit_price'];
        $startAt = Carbon::parse($params['start_at']);
        $totalMonths = (int) $params['total_months'];
        $type = (int) ($params['type'] ?? BillDetailType::Grades->value);
        $currentPrice = isset($params['current_grade_price']) ? (int) $params['current_grade_price'] : null;

        if ($type === BillDetailType::UpgradeFeeDiff->value && $currentPrice !== null) {
            $totalPrice = $this->calc->calculateUpgradeDiff($unitPrice, $currentPrice, $startAt, $totalMonths);
        } else {
            $totalPrice = $this->calc->calculateDetailTotal($unitPrice, $startAt, $totalMonths);
        }

        $expiredAt = $this->calc->calculateExpiredAt($startAt, $totalMonths);

        return [
            'total_price' => $totalPrice,
            'expired_at' => $expiredAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create a new bill with details in a transaction.
     */
    public function createBill(array $data, User $creator): Bill
    {
        return DB::transaction(function () use ($data, $creator) {
            $shop = Shop::findOrFail($data['shop_id']);

            $billNo = $this->generateBillNo();

            $bill = $this->billRepository->create([
                'no' => $billNo,
                'creator_id' => $creator->id,
                'shop_id' => $shop->id,
                'shop_sales_id' => $shop->sales_id,
                'total' => 0,
                'total_grade' => 0,
                'total_addons' => 0,
                'discount_amount' => null,
                'payment_status' => BillPaymentStatus::Pending,
            ]);

            BillStatusLog::create([
                'bill_id' => $bill->id,
                'from_status' => null,
                'to_status' => BillPaymentStatus::Pending->value,
                'operator_id' => $creator->id,
            ]);

            $details = $data['details'] ?? [];
            $discountAmount = isset($data['discount_amount']) ? (int) $data['discount_amount'] : null;
            $discountName = $data['discount_name'] ?? null;

            $this->billDetailRepository->createMany($bill, $details);

            if ($discountAmount && $discountName) {
                $this->billDetailRepository->createMany($bill, [[
                    'type' => BillDetailType::Discount->value,
                    'quantity' => 1,
                    'unit_price' => $discountAmount,
                    'total_price' => $discountAmount,
                    'name' => $discountName,
                    'start_at' => now(),
                    'expired_at' => now(),
                    'total_months' => 0,
                    'is_effective' => 1,
                ]]);
            }

            $this->recalculateBillTotals($bill, $discountAmount);

            return $bill->fresh();
        });
    }

    /**
     * Writeoff selected detail lines on a pending/unpaid bill.
     */
    public function writeoff(Bill $bill, array $detailIds, User $operator): void
    {
        if (! in_array($bill->payment_status, [BillPaymentStatus::Pending, BillPaymentStatus::Unpaid])) {
            throw ValidationException::withMessages(['bill' => '只有待審核或未付款的帳單可以銷帳']);
        }

        DB::transaction(function () use ($bill, $detailIds, $operator) {
            $this->billDetailRepository->writeoff($detailIds, $operator->id);

            $bill->refresh();
            $effectiveDetails = $this->billDetailRepository->getEffectiveByBill($bill->id);

            $totalGrade = 0;
            $totalAddons = 0;

            foreach ($effectiveDetails as $detail) {
                if (in_array($detail->type->value, [BillDetailType::Grades->value, BillDetailType::UpgradeFeeDiff->value])) {
                    $totalGrade += $detail->total_price;
                } elseif ($detail->type->value === BillDetailType::Addons->value) {
                    $totalAddons += $detail->total_price;
                }
            }

            $subtotal = $totalGrade + $totalAddons;
            $discountAmount = $bill->discount_amount;

            if ($discountAmount !== null && $discountAmount > $subtotal) {
                $discountAmount = $subtotal;
            }

            $total = max(0, $subtotal - ($discountAmount ?? 0));

            $this->billRepository->updateTotals($bill, $total, $totalGrade, $totalAddons, $discountAmount);

            // If all details are cancelled, mark bill as invalid
            if ($effectiveDetails->isEmpty()) {
                $fromStatus = $bill->payment_status;
                $this->billRepository->updateStatus($bill, BillPaymentStatus::Invalid);

                BillStatusLog::create([
                    'bill_id' => $bill->id,
                    'from_status' => $fromStatus->value,
                    'to_status' => BillPaymentStatus::Invalid->value,
                    'operator_id' => $operator->id,
                ]);
            }
        });
    }

    private function recalculateBillTotals(Bill $bill, ?int $discountAmount): void
    {
        $effectiveDetails = $this->billDetailRepository->getEffectiveByBill($bill->id);

        $totalGrade = 0;
        $totalAddons = 0;

        foreach ($effectiveDetails as $detail) {
            if (in_array($detail->type->value, [BillDetailType::Grades->value, BillDetailType::UpgradeFeeDiff->value])) {
                $totalGrade += $detail->total_price;
            } elseif ($detail->type->value === BillDetailType::Addons->value) {
                $totalAddons += $detail->total_price;
            }
        }

        $subtotal = $totalGrade + $totalAddons;
        $total = max(0, $subtotal - ($discountAmount ?? 0));

        $this->billRepository->updateTotals($bill, $total, $totalGrade, $totalAddons, $discountAmount);
    }

    /**
     * Generate a unique bill number: b{Ymd}{His}{8-digit random}, retry up to 3 times.
     */
    private function generateBillNo(): string
    {
        for ($i = 0; $i < 3; $i++) {
            $no = 'b' . now()->format('YmdHis') . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            if (! Bill::where('no', $no)->exists()) {
                return $no;
            }
        }

        throw new \RuntimeException('Failed to generate unique bill number after 3 attempts');
    }
}
