<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\BillDetailType;
use App\Enums\BillPaymentStatus;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Http\Requests\WriteoffBillRequest;
use App\Models\Bill;
use App\Models\BillDiscount;
use App\Services\BillPaymentService;
use App\Services\BillService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function __construct(
        private BillService $billService,
        private BillPaymentService $billPaymentService,
    ) {}

    #[RequiresPermission('Bill.index')]
    public function index(Request $request)
    {
        $data = $this->billService->getIndexData(
            $request->only(['no', 'payment_method', 'payment_status', 'sales_id'])
        );

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
            $request->safe()->only(['shop_id', 'details', 'discount_amount', 'discount_name', 'payment_method']),
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

    #[RequiresPermission('Bill.index')]
    public function detail(int $id): JsonResponse
    {
        $bill = Bill::with(['shop', 'creator', 'details'])->find($id);

        if (! $bill) {
            return response()->json(['message' => '帳單不存在'], 404);
        }

        $statusLabels = [
            BillPaymentStatus::Pending->value  => ['label' => '待審核', 'class' => 'bg-yellow-100 text-yellow-800'],
            BillPaymentStatus::Unpaid->value   => ['label' => '待付款', 'class' => 'bg-orange-100 text-orange-800'],
            BillPaymentStatus::Paid->value     => ['label' => '已付款', 'class' => 'bg-green-100 text-green-800'],
            BillPaymentStatus::Invalid->value  => ['label' => '已失效', 'class' => 'bg-gray-100 text-gray-500'],
        ];

        $s = $statusLabels[$bill->payment_status->value] ?? ['label' => '未知', 'class' => 'bg-gray-100 text-gray-500'];
        $typeLabels = $this->typeLabels();

        return response()->json([
            'bill' => [
                'id'             => $bill->id,
                'no'             => $bill->no,
                'shop_name'      => $bill->shop->name,
                'creator_name'   => $bill->creator?->name ?? '—',
                'payment_status' => $bill->payment_status->value,
                'status_label'   => $s['label'],
                'status_class'   => $s['class'],
                'total_grade'    => $bill->total_grade,
                'total_addons'   => $bill->total_addons,
                'discount_amount' => $bill->discount_amount,
                'total'          => $bill->total,
                'paid_at'        => $bill->paid_at?->format('Y-m-d'),
                'invoice_no'     => $bill->invoice_no,
            ],
            'details' => $bill->details->map(fn ($d) => [
                'id'           => $d->id,
                'name'         => $d->name,
                'type'         => $d->type->value,
                'type_label'   => $typeLabels[$d->type->value] ?? '未知',
                'quantity'     => $d->quantity,
                'unit_price'   => $d->unit_price,
                'total_price'  => $d->total_price,
                'start_at'     => $d->start_at?->format('Y-m-d'),
                'expired_at'   => $d->expired_at?->format('Y-m-d'),
                'is_effective' => $d->is_effective,
            ]),
        ]);
    }

    #[RequiresPermission('Bill.pay')]
    public function update(UpdateBillRequest $request, int $id): JsonResponse
    {
        $bill = Bill::find($id);

        if (! $bill) {
            return response()->json(['message' => '帳單不存在'], 404);
        }

        $this->billPaymentService->update($bill, $request->safe()->all(), $request->user());

        return response()->json(['message' => '儲存成功']);
    }

    #[RequiresPermission('Bill.pay')]
    public function pay(Request $request, int $id): JsonResponse
    {
        $bill = Bill::find($id);

        if (! $bill) {
            return response()->json(['message' => '帳單不存在'], 404);
        }

        if (! in_array($bill->payment_status, [BillPaymentStatus::Pending, BillPaymentStatus::Unpaid])) {
            return response()->json(['message' => '此帳單狀態無法執行付款'], 422);
        }

        $this->billPaymentService->pay($bill, $request->user());

        return response()->json(['message' => '付款成功']);
    }

    #[RequiresPermission('Bill.index')]
    public function quotation(int $id)
    {
        $bill = Bill::with(['shop', 'details' => fn ($q) => $q->where('is_effective', 1)])->find($id);

        if (! $bill) {
            abort(404);
        }

        $typeLabels = $this->typeLabels();

        $details = $bill->details->map(fn ($d) => [
            'name'        => $d->name,
            'type_label'  => $typeLabels[$d->type->value] ?? '—',
            'type'        => $d->type->value,
            'total_price' => $d->total_price,
            'start_at'    => $d->start_at?->format('Y-m-d'),
            'expired_at'  => $d->expired_at?->format('Y-m-d'),
        ]);

        $filename = now()->format('Y-m-d').'_'.$bill->shop->name.'_'.$bill->id.'_報價單.pdf';

        return Pdf::loadView('admin.bills.quotation', [
            'bill'    => $bill,
            'details' => $details,
        ])->download($filename);
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

    private function typeLabels(): array
    {
        return [
            BillDetailType::Grades->value        => '版本',
            BillDetailType::UpgradeFeeDiff->value => '升級補差額',
            BillDetailType::Addons->value         => '加購功能',
            BillDetailType::Discount->value       => '折抵',
        ];
    }
}
