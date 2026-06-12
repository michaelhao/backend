<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Http\Requests\WriteoffBillRequest;
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
        return view('admin.bills.create', $this->billService->getCreatePageData());
    }

    #[RequiresPermission('Bill.create')]
    public function store(StoreBillRequest $request)
    {
        $bill = $this->billService->createBill(
            $request->safe()->only(['shop_id', 'details', 'discount_amount', 'discount_id', 'payment_method']),
            $request->user(),
        );

        return redirect()->route('bills.index')->with('success', "帳單 {$bill->no} 已建立");
    }

    #[RequiresPermission('Bill.index')]
    public function shopSearch(Request $request): JsonResponse
    {
        $keyword = trim($request->query('keyword', ''));

        if ($keyword === '') {
            return response()->json(['message' => '關鍵字必填'], 422);
        }

        $shops = $this->billService->shopSearch($keyword);

        return response()->json([
            'shops' => $shops->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'label' => "{$s->id} — {$s->name}",
            ]),
        ]);
    }

    #[RequiresPermission('Bill.index')]
    public function shopInfo(Request $request): JsonResponse
    {
        $request->validate([
            'shop_id' => ['required', 'integer'],
        ]);

        $data = $this->billService->shopInfo((int) $request->query('shop_id'));

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
        $data = $this->billService->getDetailData($id);

        if ($data === null) {
            return response()->json(['message' => '帳單不存在'], 404);
        }

        return response()->json($data);
    }

    #[RequiresPermission('Bill.pay')]
    public function update(UpdateBillRequest $request, int $id): JsonResponse
    {
        $bill = $this->billService->getById($id);

        if (! $bill) {
            return response()->json(['message' => '帳單不存在'], 404);
        }

        $this->billPaymentService->update($bill, $request->safe()->all(), $request->user());

        return response()->json(['message' => '儲存成功']);
    }

    #[RequiresPermission('Bill.index')]
    public function quotation(int $id)
    {
        $data = $this->billService->getQuotationData($id);

        if ($data === null) {
            abort(404);
        }

        return Pdf::loadView('admin.bills.quotation', [
            'bill'    => $data['bill'],
            'details' => $data['details'],
        ])->download($data['filename']);
    }

    #[RequiresPermission('Bill.writeoff')]
    public function writeoff(WriteoffBillRequest $request, int $id): JsonResponse
    {
        $bill = $this->billService->getById($id);

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
