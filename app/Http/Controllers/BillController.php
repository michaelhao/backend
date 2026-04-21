<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\BillPaymentStatus;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\WriteoffBillRequest;
use App\Models\Bill;
use App\Models\BillDiscount;
use App\Services\BillPaymentService;
use App\Services\BillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function __construct(
        private BillService $billService,
        private BillPaymentService $billPaymentService,
    ) {}

    #[RequiresPermission('Bill.index')]
    public function index()
    {
        $data = $this->billService->getIndexData();

        return view('admin.bills.index', $data);
    }

    #[RequiresPermission('Bill.create')]
    public function create()
    {
        $discounts = BillDiscount::orderBy('id')->get();

        return view('admin.bills.create', compact('discounts'));
    }

    #[RequiresPermission('Bill.create')]
    public function store(StoreBillRequest $request)
    {
        $bill = $this->billService->createBill(
            $request->safe()->only(['shop_id', 'details', 'discount_amount', 'discount_name']),
            $request->user(),
        );

        return redirect()->route('bills.index')->with('success', "帳單 {$bill->no} 已建立");
    }

    #[RequiresPermission('Bill.index')]
    public function shopSearch(Request $request): JsonResponse
    {
        $keyword = $request->query('keyword', '');

        if (strlen(trim($keyword)) === 0) {
            return response()->json(['shops' => []]);
        }

        $shops = $this->billService->shopSearch(trim($keyword));

        return response()->json([
            'shops' => $shops->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code ?? '',
                'label' => "{$s->id} — {$s->name}" . ($s->code ? "（{$s->code}）" : ''),
            ]),
        ]);
    }

    #[RequiresPermission('Bill.index')]
    public function shopInfo(Request $request): JsonResponse
    {
        $shopId = (int) $request->query('shop_id');

        try {
            $data = $this->billService->shopInfo($shopId);

            return response()->json([
                'shop' => [
                    'id' => $data['shop']->id,
                    'name' => $data['shop']->name,
                    'grade' => $data['shop']->grade?->name,
                    'grade_id' => $data['shop']->grade_id,
                    'grade_price' => $data['shop']->grade?->price,
                    'grade_weight' => $data['shop']->grade?->weight,
                    'status' => $data['shop']->status->name,
                    'expired_at' => $data['shop']->expired_at?->format('Y-m-d H:i:s'),
                ],
                'pending_bill_count' => $data['pending_bill_count'],
                'grades' => $data['grades'],
                'addons' => $data['addons'],
                'shop_addons' => $data['shop_addons']->map(fn ($sa) => [
                    'addon_id' => $sa->addon_id,
                    'expired_at' => $sa->expired_at?->format('Y-m-d'),
                ]),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    #[RequiresPermission('Bill.create')]
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'unit_price' => ['required', 'integer'],
            'start_at' => ['required', 'date'],
            'total_months' => ['required', 'integer', 'min:0', 'max:36'],
            'type' => ['nullable', 'integer'],
            'current_grade_price' => ['nullable', 'integer'],
        ]);

        $result = $this->billService->calculateDetail($request->only([
            'unit_price', 'start_at', 'total_months', 'type', 'current_grade_price',
        ]));

        return response()->json($result);
    }

    #[RequiresPermission('Bill.pay')]
    public function pay(int $id): JsonResponse
    {
        $bill = Bill::find($id);

        if (! $bill) {
            return response()->json(['message' => '帳單不存在'], 404);
        }

        if (! in_array($bill->payment_status, [BillPaymentStatus::Pending, BillPaymentStatus::Unpaid])) {
            return response()->json(['message' => '此帳單狀態無法執行付款'], 422);
        }

        $this->billPaymentService->pay($bill, request()->user());

        return response()->json(['message' => '付款成功']);
    }

    #[RequiresPermission('Bill.writeoff')]
    public function writeoff(WriteoffBillRequest $request, int $id): JsonResponse
    {
        $bill = Bill::find($id);

        if (! $bill) {
            return response()->json(['message' => '帳單不存在'], 404);
        }

        $this->billService->writeoff(
            $bill,
            $request->safe()->only(['detail_ids'])['detail_ids'],
            $request->user(),
        );

        return response()->json(['message' => '銷帳成功']);
    }
}
