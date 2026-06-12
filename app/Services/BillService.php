<?php

namespace App\Services;

use App\Enums\BillDetailType;
use App\Enums\BillPaymentStatus;
use App\Models\Bill;
use App\Models\BillDetail;
use App\Models\Shop;
use App\Models\User;
use App\Repositories\AddonRepository;
use App\Repositories\BillDetailRepository;
use App\Repositories\BillDiscountRepository;
use App\Repositories\BillRepository;
use App\Repositories\GradeRepository;
use App\Repositories\ShopAddonRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillService
{
    public function __construct(
        private BillRepository $billRepository,
        private BillDetailRepository $billDetailRepository,
        private BillCalculationService $calc,
        private UserRepository $userRepository,
        private BillDiscountRepository $billDiscountRepository,
        private ShopRepository $shopRepository,
        private ShopAddonRepository $shopAddonRepository,
        private GradeRepository $gradeRepository,
        private AddonRepository $addonRepository,
    ) {}

    /**
     * @param  array{no?: string, payment_method?: string, payment_status?: string, sales_id?: string}  $filters
     * @return array{bills: LengthAwarePaginator, filters: array, salesUsers: Collection}
     */
    public function getIndexData(array $filters): array
    {
        return [
            'bills' => $this->billRepository->paginate(20, $filters),
            'filters' => $filters,
            'salesUsers' => $this->userRepository->getOrderedByName(),
        ];
    }

    public function getCreatePageData(): array
    {
        return [
            'discounts' => $this->billDiscountRepository->getAllOrdered(),
        ];
    }

    public function getById(int $id): ?Bill
    {
        return $this->billRepository->getById($id);
    }

    /**
     * Assemble the bill detail modal payload (effective and cancelled lines).
     *
     * @return array{bill: array, details: \Illuminate\Support\Collection}|null
     */
    public function getDetailData(int $id): ?array
    {
        $bill = $this->billRepository->getByIdWithShopCreatorDetails($id);

        if (! $bill) {
            return null;
        }

        return [
            'bill' => [
                'id' => $bill->id,
                'no' => $bill->no,
                'shop_name' => $bill->shop->name,
                'creator_name' => $bill->creator?->name ?? '—',
                'payment_status' => $bill->payment_status->value,
                'status_label' => $bill->payment_status->label(),
                'status_class' => $bill->payment_status->badgeClass(),
                'total_grade' => $bill->total_grade,
                'total_addons' => $bill->total_addons,
                'discount_amount' => $bill->discount_amount,
                'total' => $bill->total,
                'paid_at' => $bill->paid_at?->format('Y-m-d'),
                'invoice_no' => $bill->invoice_no,
            ],
            'details' => $bill->details->map(fn (BillDetail $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'type' => $d->type->value,
                'type_label' => $d->type->label(),
                'quantity' => $d->quantity,
                'unit_price' => $d->unit_price,
                'total_price' => $d->total_price,
                'start_at' => $d->start_at?->format('Y-m-d'),
                'expired_at' => $d->expired_at?->format('Y-m-d'),
                'is_effective' => $d->is_effective,
            ]),
        ];
    }

    /**
     * Assemble the data needed to render the quotation PDF.
     *
     * @return array{bill: Bill, details: Collection, filename: string}|null
     */
    public function getQuotationData(int $id): ?array
    {
        $bill = $this->billRepository->getByIdWithEffectiveDetails($id);

        if (! $bill) {
            return null;
        }

        $details = $bill->details->map(fn (BillDetail $d) => [
            'name'        => $d->name,
            'type_label'  => $d->type->label(),
            'type'        => $d->type->value,
            'total_price' => $d->total_price,
            'start_at'    => $d->start_at?->format('Y-m-d'),
            'expired_at'  => $d->expired_at?->format('Y-m-d'),
        ]);

        $safeShopName = preg_replace('/[^\p{L}\p{N}\-_]+/u', '_', $bill->shop->name) ?: 'shop';
        $filename = now()->format('Y-m-d').'_'.$safeShopName.'_'.$bill->id.'_報價單.pdf';

        return [
            'bill' => $bill,
            'details' => $details,
            'filename' => $filename,
        ];
    }

    /**
     * AJAX: search shops by id, code, or name keyword (max 10).
     */
    public function shopSearch(string $keyword): Collection
    {
        return $this->shopRepository->searchByIdOrName($keyword, 10);
    }

    /**
     * AJAX: validate shop exists and has sales_id.
     *
     * @return array{shop: Shop, pending_bill_count: int}
     *
     */
    public function shopInfo(int $shopId): array
    {
        $shop = $this->shopRepository->getByIdWithGradeAndSales($shopId);

        if (! $shop) {
            throw ValidationException::withMessages(['shop_id' => '商店不存在']);
        }

        if (! $shop->sales_id) {
            throw ValidationException::withMessages(['shop_id' => '此商店尚未設定負責業務，無法建立帳單']);
        }

        return [
            'shop' => $shop,
            'pending_bill_count' => $this->billRepository->getPendingOrUnpaidCountForShop($shopId),
            'grades' => $this->gradeRepository->getAllOrderedByWeight(['id', 'name', 'price', 'weight']),
            'addons' => $this->addonRepository->getAllOrderedByName(['id', 'name', 'price', 'type']),
            'shop_addons' => $this->shopAddonRepository->getEnabledWithAddonForShop($shopId),
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
            $shop = $this->shopRepository->getByIdWithGradeOrFail((int) $data['shop_id']);

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
                'payment_method' => $data['payment_method'] ?? null,
                'payment_status' => BillPaymentStatus::Pending,
            ]);

            $this->billRepository->createStatusLog([
                'bill_id' => $bill->id,
                'from_status' => null,
                'to_status' => BillPaymentStatus::Pending->value,
                'operator_id' => $creator->id,
            ]);

            $details = $this->sanitizeDetails($shop, $data['details'] ?? []);
            $discountAmount = isset($data['discount_amount']) ? (int) $data['discount_amount'] : null;
            $discountId = isset($data['discount_id']) ? (int) $data['discount_id'] : null;

            $this->billDetailRepository->createBillDetails($bill, $details);

            if ($discountAmount && $discountId) {
                $subtotal = collect($details)->sum('total_price');
                if ($discountAmount > $subtotal) {
                    throw ValidationException::withMessages(['discount_amount' => '折抵金額不得大於小計']);
                }

                $discount = $this->billDiscountRepository->getByIdOrFail($discountId);

                $this->billDetailRepository->createBillDetails($bill, [[
                    'type' => BillDetailType::Discount->value,
                    'quantity' => 1,
                    'unit_price' => $discountAmount,
                    'total_price' => $discountAmount,
                    'name' => $discount->name,
                    'start_at' => null,
                    'expired_at' => null,
                    'total_months' => null,
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
            throw ValidationException::withMessages(['bill' => '只有待審核或待付款的帳單可以銷帳']);
        }

        DB::transaction(function () use ($bill, $detailIds, $operator) {
            $this->billDetailRepository->writeoff($bill->id, $detailIds, $operator->id);

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
                $this->billDetailRepository->syncDiscountAmount($bill->id, $discountAmount);
            }

            $total = max(0, $subtotal - ($discountAmount ?? 0));

            $this->billRepository->updateTotals($bill, $total, $totalGrade, $totalAddons, $discountAmount);

            // If all details are cancelled, mark bill as invalid
            if ($effectiveDetails->isEmpty()) {
                $fromStatus = $bill->payment_status;
                $this->billRepository->updateStatus($bill, BillPaymentStatus::Invalid);

                $this->billRepository->createStatusLog([
                    'bill_id' => $bill->id,
                    'from_status' => $fromStatus->value,
                    'to_status' => BillPaymentStatus::Invalid->value,
                    'operator_id' => $operator->id,
                ]);
            }
        });
    }

    /**
     * Recompute unit_price / total_price / expired_at server-side.
     * Client-supplied totals are not trusted; only identifiers and scheduling inputs are kept.
     */
    private function sanitizeDetails(Shop $shop, array $details): array
    {
        $currentGradePrice = $shop->grade?->price ?? 0;

        return array_map(function (array $d) use ($currentGradePrice) {
            $type = (int) $d['type'];
            $startAt = Carbon::parse($d['start_at']);
            $totalMonths = (int) $d['total_months'];

            if ($type === BillDetailType::Addons->value) {
                $addon = $this->addonRepository->getByIdOrFail((int) $d['addon_id']);
                $unitPrice = (int) $addon->price;
                $name = $addon->name;
                // total_price = 單份期間金額 × quantity（與前端顯示一致）
                $totalPrice = $this->calc->calculateDetailTotal($unitPrice, $startAt, $totalMonths) * (int) $d['quantity'];
            } elseif ($type === BillDetailType::UpgradeFeeDiff->value) {
                $grade = $this->gradeRepository->getByIdOrFail((int) $d['grade_id']);
                $unitPrice = (int) $grade->price;
                $name = $grade->name;
                $totalPrice = $this->calc->calculateUpgradeDiff($unitPrice, $currentGradePrice, $startAt, $totalMonths);
            } else {
                $grade = $this->gradeRepository->getByIdOrFail((int) $d['grade_id']);
                $unitPrice = (int) $grade->price;
                $name = $grade->name;
                $totalPrice = $this->calc->calculateDetailTotal($unitPrice, $startAt, $totalMonths);
            }

            $expiredAt = $this->calc->calculateExpiredAt($startAt, $totalMonths);

            return [
                'type' => $type,
                'grade_id' => $d['grade_id'] ?? null,
                'addon_id' => $d['addon_id'] ?? null,
                'payment_type' => $d['payment_type'] ?? null,
                'quantity' => (int) $d['quantity'],
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'name' => $name,
                'start_at' => $startAt,
                'expired_at' => $expiredAt,
                'total_months' => $totalMonths,
                'memo' => $d['memo'] ?? null,
                'is_effective' => 1,
            ];
        }, $details);
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
        $candidates = [];
        for ($i = 0; $i < 3; $i++) {
            $candidates[] = 'b' . now()->format('YmdHis') . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        }

        $taken = $this->billRepository->getTakenNos($candidates);
        foreach ($candidates as $no) {
            if (! in_array($no, $taken, true)) {
                return $no;
            }
        }

        throw new \RuntimeException('Failed to generate unique bill number after 3 attempts');
    }
}
