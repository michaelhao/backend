<?php

namespace Tests\Feature\Bill;

use App\Enums\BillDetailType;
use App\Enums\BillPaymentStatus;
use App\Models\Bill;
use App\Models\BillDetail;
use App\Models\BillFutureEffect;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Services\BillPaymentService;
use App\Services\BillService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BillEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $this->seed(PermissionSeeder::class);
        $role = Role::where('name', 'Admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);
        $user->loadPermissionsToSession();

        return $user;
    }

    private function makeBillWithDetail(User $user, array $billAttrs = [], array $detailAttrs = []): array
    {
        $shop = Shop::factory()->create(['sales_id' => $user->id]);
        $bill = Bill::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'creator_id' => $user->id,
            'shop_sales_id' => $user->id,
        ], $billAttrs));
        $detail = BillDetail::factory()->create(array_merge([
            'bill_id' => $bill->id,
        ], $detailAttrs));

        return [$bill, $detail, $shop];
    }

    // ─── 帳單明細 Modal（GET /bills/{id}/detail）─────────────────────────────

    public function test_detail_returns_bill_json_with_details(): void
    {
        $user = $this->actingAsAdmin();
        [$bill, $detail] = $this->makeBillWithDetail($user);

        $response = $this->getJson(route('bills.detail', $bill->id));

        $response->assertOk()
            ->assertJsonPath('bill.no', $bill->no)
            ->assertJsonPath('bill.status_label', '待審核')
            ->assertJsonPath('details.0.id', $detail->id)
            ->assertJsonStructure([
                'bill' => ['id', 'no', 'shop_name', 'creator_name', 'payment_status', 'status_label', 'total_grade', 'total_addons', 'discount_amount', 'total'],
                'details' => [['id', 'name', 'type', 'type_label', 'quantity', 'unit_price', 'total_price', 'start_at', 'expired_at', 'is_effective']],
            ]);
    }

    public function test_detail_returns_404_for_missing_bill(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('bills.detail', 999999))->assertStatus(404);
    }

    // ─── 匯出報價單（GET /bills/{id}/quotation）──────────────────────────────

    public function test_quotation_downloads_pdf_attachment(): void
    {
        $user = $this->actingAsAdmin();
        [$bill] = $this->makeBillWithDetail($user);

        $response = $this->get(route('bills.quotation', $bill->id));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_quotation_returns_404_for_missing_bill(): void
    {
        $this->actingAsAdmin();

        $this->get(route('bills.quotation', 999999))->assertStatus(404);
    }

    public function test_quotation_excludes_ineffective_details(): void
    {
        $user = $this->actingAsAdmin();
        [$bill] = $this->makeBillWithDetail($user, [], ['name' => '有效項目', 'is_effective' => 1]);
        BillDetail::factory()->create([
            'bill_id' => $bill->id,
            'name' => '作廢項目',
            'is_effective' => 0,
            'canceled_at' => now(),
        ]);

        $data = app(BillService::class)->getQuotationData($bill->id);

        $names = collect($data['details'])->pluck('name');
        $this->assertTrue($names->contains('有效項目'));
        $this->assertFalse($names->contains('作廢項目'));
        $this->assertStringContainsString("_{$bill->id}_報價單.pdf", $data['filename']);
    }

    // ─── 商店搜尋（GET /bills/shop-search）───────────────────────────────────

    public function test_shop_search_requires_keyword(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('bills.shop-search'))->assertStatus(422);
    }

    public function test_shop_search_numeric_keyword_matches_id_exactly(): void
    {
        $this->actingAsAdmin();
        $shop = Shop::factory()->create();
        Shop::factory()->create(['name' => "店名含數字{$shop->id}"]);

        $response = $this->getJson(route('bills.shop-search', ['keyword' => $shop->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'shops')
            ->assertJsonPath('shops.0.id', $shop->id);
    }

    public function test_shop_search_text_keyword_matches_name_fuzzy(): void
    {
        $this->actingAsAdmin();
        Shop::factory()->create(['name' => '美味牛肉麵店']);
        Shop::factory()->create(['name' => '快樂早餐店']);

        $response = $this->getJson(route('bills.shop-search', ['keyword' => '牛肉']));

        $response->assertOk()->assertJsonCount(1, 'shops');
    }

    public function test_shop_search_limits_results_to_ten(): void
    {
        $this->actingAsAdmin();
        Shop::factory()->count(12)->create(['name' => '連鎖商店分店']);

        $response = $this->getJson(route('bills.shop-search', ['keyword' => '連鎖商店']));

        $response->assertOk()->assertJsonCount(10, 'shops');
    }

    // ─── 商店資訊（GET /bills/shop-info）─────────────────────────────────────

    public function test_shop_info_fails_for_missing_shop(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('bills.shop-info', ['shop_id' => 999999]))->assertStatus(422);
    }

    public function test_shop_info_fails_when_shop_has_no_sales(): void
    {
        $this->actingAsAdmin();
        $shop = Shop::factory()->create(['sales_id' => null]);

        $this->getJson(route('bills.shop-info', ['shop_id' => $shop->id]))->assertStatus(422);
    }

    public function test_shop_info_returns_shop_with_pending_bill_count(): void
    {
        $user = $this->actingAsAdmin();
        [$bill, , $shop] = $this->makeBillWithDetail($user, ['payment_status' => BillPaymentStatus::Unpaid]);

        $response = $this->getJson(route('bills.shop-info', ['shop_id' => $shop->id]));

        $response->assertOk()
            ->assertJsonPath('shop.id', $shop->id)
            ->assertJsonPath('pending_bill_count', 1)
            ->assertJsonStructure(['shop', 'pending_bill_count', 'grades', 'addons', 'shop_addons']);
    }

    // ─── 試算（GET /bills/calculate）─────────────────────────────────────────

    public function test_calculate_validates_required_fields(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('bills.calculate'))->assertStatus(422);
    }

    public function test_calculate_returns_total_price_and_expired_at(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson(route('bills.calculate', [
            'unit_price' => 3000,
            'start_at' => '2026-08-01',
            'total_months' => 12,
        ]));

        $response->assertOk()
            ->assertJsonPath('total_price', 36000)
            ->assertJsonPath('expired_at', '2027-07-31 23:59:59');
    }

    // ─── 銷帳全作廢 → invalid + status log ───────────────────────────────────

    public function test_writeoff_all_details_marks_bill_invalid_and_logs_status(): void
    {
        $user = $this->actingAsAdmin();
        [$bill, $detail] = $this->makeBillWithDetail($user, [
            'payment_status' => BillPaymentStatus::Unpaid,
            'total' => 1000,
            'total_grade' => 1000,
        ], ['total_price' => 1000]);

        $this->postJson(route('bills.writeoff', $bill->id), [
            'detail_ids' => [$detail->id],
        ])->assertOk();

        $this->assertEquals(BillPaymentStatus::Invalid, $bill->fresh()->payment_status);
        $this->assertDatabaseHas('bills_status_logs', [
            'bill_id' => $bill->id,
            'from_status' => BillPaymentStatus::Unpaid->value,
            'to_status' => BillPaymentStatus::Invalid->value,
            'operator_id' => $user->id,
        ]);

        $freshDetail = $detail->fresh();
        $this->assertSame(0, $freshDetail->is_effective);
        $this->assertNotNull($freshDetail->canceled_at);
        $this->assertSame($user->id, $freshDetail->canceled_by);
    }

    // ─── 付款鎖（429）────────────────────────────────────────────────────────

    public function test_update_to_paid_returns_429_when_lock_is_held(): void
    {
        $user = $this->actingAsAdmin();
        [$bill] = $this->makeBillWithDetail($user, ['payment_status' => BillPaymentStatus::Unpaid]);

        $lock = Cache::lock("bill_pay_{$bill->id}", 10);
        $this->assertTrue($lock->get());

        try {
            $this->patchJson(route('bills.update', $bill->id), [
                'payment_status' => BillPaymentStatus::Paid->value,
            ])->assertStatus(429);

            $this->assertEquals(BillPaymentStatus::Unpaid, $bill->fresh()->payment_status);
        } finally {
            $lock->release();
        }
    }

    // ─── 排程容錯：單筆失敗不中斷 ────────────────────────────────────────────

    public function test_process_future_effects_continues_after_single_failure(): void
    {
        $user = $this->actingAsAdmin();
        $grade = Grade::factory()->create(['price' => 1000, 'weight' => 10]);

        // 第一筆：bill 指向不存在的 shop，installDetail 會丟例外
        $brokenBill = Bill::factory()->create([
            'shop_id' => 999999,
            'creator_id' => $user->id,
            'shop_sales_id' => $user->id,
            'payment_status' => BillPaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $brokenDetail = BillDetail::factory()->create([
            'bill_id' => $brokenBill->id,
            'grade_id' => $grade->id,
            'start_at' => today()->subDay(),
        ]);
        BillFutureEffect::create([
            'bill_id' => $brokenBill->id,
            'bill_detail_id' => $brokenDetail->id,
            'execute_at' => today()->subDay()->toDateString(),
        ]);

        // 第二筆：正常資料，應照常安裝
        [$okBill, $okDetail] = $this->makeBillWithDetail($user, [
            'payment_status' => BillPaymentStatus::Paid,
            'paid_at' => now(),
        ], [
            'grade_id' => $grade->id,
            'start_at' => today()->subDay(),
        ]);
        BillFutureEffect::create([
            'bill_id' => $okBill->id,
            'bill_detail_id' => $okDetail->id,
            'execute_at' => today()->subDay()->toDateString(),
        ]);

        app(BillPaymentService::class)->processFutureEffects();

        $this->assertNull(BillFutureEffect::where('bill_detail_id', $brokenDetail->id)->first()->finished_at);
        $this->assertNotNull(BillFutureEffect::where('bill_detail_id', $okDetail->id)->first()->finished_at);
        $this->assertNotNull($okDetail->fresh()->applied_at);
    }
}
