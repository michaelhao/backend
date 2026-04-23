<?php

namespace Tests\Feature;

use App\Enums\BillDetailType;
use App\Enums\BillPaymentStatus;
use App\Models\Bill;
use App\Models\BillDetail;
use App\Models\BillDiscount;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillStoreTest extends TestCase
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

    private function makeShop(User $sales, int $weight = 10, int $price = 1000, ?Carbon $expiredAt = null): array
    {
        $grade = Grade::factory()->create(['price' => $price, 'weight' => $weight]);
        $shop = Shop::factory()->create([
            'grade_id' => $grade->id,
            'sales_id' => $sales->id,
            'expired_at' => $expiredAt ?? now()->addMonth(),
        ]);

        return [$shop, $grade];
    }

    public function test_stored_detail_name_is_sourced_from_grade_not_client_payload(): void
    {
        $user = $this->actingAsAdmin();
        [$shop] = $this->makeShop($user);
        $newGrade = Grade::factory()->create(['price' => 2000, 'weight' => 20, 'name' => '正式版本名稱']);

        $this->post(route('bills.store'), [
            'shop_id' => $shop->id,
            'payment_method' => 2,
            'details' => [[
                'type' => BillDetailType::Grades->value,
                'grade_id' => $newGrade->id,
                'quantity' => 1,
                'name' => '偽造的名稱（駭客注入）',
                'start_at' => today()->format('Y-m-d'),
                'total_months' => 12,
            ]],
        ])->assertRedirect();

        $detail = BillDetail::where('grade_id', $newGrade->id)->firstOrFail();
        $this->assertSame('正式版本名稱', $detail->name);
    }

    public function test_downgrade_with_start_at_before_expiry_plus_one_is_rejected(): void
    {
        $user = $this->actingAsAdmin();
        [$shop] = $this->makeShop($user, weight: 20, expiredAt: now()->addMonth());
        $lowerGrade = Grade::factory()->create(['price' => 500, 'weight' => 10]);

        $response = $this->post(route('bills.store'), [
            'shop_id' => $shop->id,
            'payment_method' => 2,
            'details' => [[
                'type' => BillDetailType::Grades->value,
                'grade_id' => $lowerGrade->id,
                'quantity' => 1,
                'start_at' => today()->format('Y-m-d'),
                'total_months' => 12,
            ]],
        ]);

        $response->assertSessionHasErrors('details.0.start_at');
        $this->assertDatabaseCount('bills', 0);
    }

    public function test_upgrade_with_start_at_today_is_accepted(): void
    {
        $user = $this->actingAsAdmin();
        [$shop] = $this->makeShop($user, weight: 10, price: 1000, expiredAt: now()->addMonth());
        $higherGrade = Grade::factory()->create(['price' => 3000, 'weight' => 30]);

        $this->post(route('bills.store'), [
            'shop_id' => $shop->id,
            'payment_method' => 2,
            'details' => [[
                'type' => BillDetailType::Grades->value,
                'grade_id' => $higherGrade->id,
                'quantity' => 1,
                'start_at' => today()->format('Y-m-d'),
                'total_months' => 12,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseCount('bills', 1);
    }

    public function test_writeoff_shrinks_discount_detail_row_when_subtotal_drops_below_discount(): void
    {
        $user = $this->actingAsAdmin();
        [$shop] = $this->makeShop($user);

        $bill = Bill::factory()->create([
            'shop_id' => $shop->id,
            'creator_id' => $user->id,
            'shop_sales_id' => $user->id,
            'payment_status' => BillPaymentStatus::Pending,
            'total' => 850,
            'total_grade' => 1050,
            'discount_amount' => 200,
        ]);

        $gradeDetail = BillDetail::factory()->create([
            'bill_id' => $bill->id,
            'type' => BillDetailType::Grades,
            'total_price' => 1000,
            'is_effective' => 1,
        ]);

        $discountRow = BillDetail::factory()->create([
            'bill_id' => $bill->id,
            'type' => BillDetailType::Discount,
            'unit_price' => 200,
            'total_price' => 200,
            'name' => '優惠券',
            'start_at' => null,
            'expired_at' => null,
            'total_months' => null,
            'is_effective' => 1,
        ]);

        BillDetail::factory()->create([
            'bill_id' => $bill->id,
            'type' => BillDetailType::Grades,
            'total_price' => 50,
            'is_effective' => 1,
        ]);

        $this->postJson(route('bills.writeoff', $bill->id), [
            'detail_ids' => [$gradeDetail->id],
        ])->assertOk();

        $freshBill = $bill->fresh();
        $this->assertSame(50, $freshBill->discount_amount, 'bills.discount_amount should shrink to subtotal');

        $freshDiscountRow = $discountRow->fresh();
        $this->assertSame(50, $freshDiscountRow->unit_price);
        $this->assertSame(50, $freshDiscountRow->total_price);
    }

    public function test_discount_detail_stored_with_null_start_expired_and_months(): void
    {
        $user = $this->actingAsAdmin();
        [$shop] = $this->makeShop($user, weight: 10, price: 1000);
        $grade = Grade::factory()->create(['price' => 2000, 'weight' => 20]);
        $discount = BillDiscount::create(['name' => '優惠券']);

        $this->post(route('bills.store'), [
            'shop_id' => $shop->id,
            'payment_method' => 2,
            'discount_id' => $discount->id,
            'discount_amount' => 100,
            'details' => [[
                'type' => BillDetailType::Grades->value,
                'grade_id' => $grade->id,
                'quantity' => 1,
                'start_at' => today()->format('Y-m-d'),
                'total_months' => 12,
            ]],
        ])->assertRedirect();

        $row = BillDetail::where('type', BillDetailType::Discount->value)->firstOrFail();
        $this->assertNull($row->start_at);
        $this->assertNull($row->expired_at);
        $this->assertNull($row->total_months);
    }
}
