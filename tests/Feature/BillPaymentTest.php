<?php

namespace Tests\Feature;

use App\Enums\BillDetailType;
use App\Enums\BillPaymentStatus;
use App\Models\Bill;
use App\Models\BillDetail;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function seedPermissions(): void
    {
        $this->seed(PermissionSeeder::class);
    }

    private function createAdminUser(): User
    {
        $role = Role::where('name', 'Admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);
        $user->loadPermissionsToSession();

        return $user;
    }

    private function createShopWithGrade(User $salesUser): array
    {
        $grade = Grade::factory()->create(['price' => 1000, 'weight' => 10]);
        $shop = Shop::factory()->create([
            'grade_id' => $grade->id,
            'sales_id' => $salesUser->id,
            'expired_at' => now()->addMonth(),
        ]);

        return [$shop, $grade];
    }

    public function test_pay_bill_changes_status_to_paid(): void
    {
        $this->seedPermissions();
        $user = $this->createAdminUser();
        [$shop, $grade] = $this->createShopWithGrade($user);

        $bill = Bill::factory()->create([
            'shop_id' => $shop->id,
            'creator_id' => $user->id,
            'shop_sales_id' => $user->id,
            'payment_status' => BillPaymentStatus::Pending,
        ]);

        $detail = BillDetail::factory()->create([
            'bill_id' => $bill->id,
            'type' => BillDetailType::Grades,
            'name' => $grade->name,
            'start_at' => today(),
            'expired_at' => today()->addMonth()->endOfMonth()->setTime(23, 59, 59),
            'total_months' => 1,
            'is_effective' => 1,
        ]);

        $this->post(route('bills.pay', $bill->id));

        $this->assertEquals(BillPaymentStatus::Paid, $bill->fresh()->payment_status);
        $this->assertNotNull(BillDetail::find($detail->id)->applied_at);
    }

    public function test_future_detail_creates_future_effect_record(): void
    {
        $this->seedPermissions();
        $user = $this->createAdminUser();
        [$shop, $grade] = $this->createShopWithGrade($user);

        $bill = Bill::factory()->create([
            'shop_id' => $shop->id,
            'creator_id' => $user->id,
            'shop_sales_id' => $user->id,
            'payment_status' => BillPaymentStatus::Pending,
        ]);

        $futureDate = today()->addDays(5);
        $detail = BillDetail::factory()->create([
            'bill_id' => $bill->id,
            'type' => BillDetailType::Grades,
            'name' => $grade->name,
            'start_at' => $futureDate,
            'expired_at' => $futureDate->copy()->addMonth()->endOfMonth()->setTime(23, 59, 59),
            'total_months' => 1,
            'is_effective' => 1,
        ]);

        $this->post(route('bills.pay', $bill->id));

        $this->assertDatabaseHas('bills_future_effect', [
            'bill_id' => $bill->id,
            'bill_detail_id' => $detail->id,
        ]);
        $this->assertNull(BillDetail::find($detail->id)->applied_at);
    }

    public function test_install_detail_is_idempotent(): void
    {
        $this->seedPermissions();
        $user = $this->createAdminUser();
        [$shop, $grade] = $this->createShopWithGrade($user);

        $bill = Bill::factory()->create([
            'shop_id' => $shop->id,
            'creator_id' => $user->id,
            'shop_sales_id' => $user->id,
            'payment_status' => BillPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $appliedAt = now()->subHour();
        $detail = BillDetail::factory()->create([
            'bill_id' => $bill->id,
            'type' => BillDetailType::Grades,
            'name' => $grade->name,
            'start_at' => today(),
            'expired_at' => today()->addMonth()->endOfMonth()->setTime(23, 59, 59),
            'total_months' => 1,
            'is_effective' => 1,
            'applied_at' => $appliedAt,
        ]);

        $originalExpiredAt = $shop->expired_at->format('Y-m-d H:i:s');

        $service = app(\App\Services\BillPaymentService::class);
        $service->installDetail($detail);

        // expired_at on shop should NOT change since applied_at was already set
        $this->assertEquals($originalExpiredAt, $shop->fresh()->expired_at->format('Y-m-d H:i:s'));
    }

    public function test_writeoff_marks_detail_ineffective(): void
    {
        $this->seedPermissions();
        $user = $this->createAdminUser();
        [$shop, $grade] = $this->createShopWithGrade($user);

        $bill = Bill::factory()->create([
            'shop_id' => $shop->id,
            'creator_id' => $user->id,
            'shop_sales_id' => $user->id,
            'payment_status' => BillPaymentStatus::Pending,
            'total' => 1000,
            'total_grade' => 1000,
        ]);

        $detail = BillDetail::factory()->create([
            'bill_id' => $bill->id,
            'type' => BillDetailType::Grades,
            'total_price' => 1000,
            'is_effective' => 1,
        ]);

        $this->postJson(route('bills.writeoff', $bill->id), [
            'detail_ids' => [$detail->id],
        ])->assertOk();

        $this->assertEquals(0, BillDetail::find($detail->id)->is_effective);
        $this->assertEquals(BillPaymentStatus::Invalid, $bill->fresh()->payment_status);
    }

    public function test_cannot_pay_already_paid_bill(): void
    {
        $this->seedPermissions();
        $user = $this->createAdminUser();
        [$shop, $grade] = $this->createShopWithGrade($user);

        $bill = Bill::factory()->create([
            'shop_id' => $shop->id,
            'creator_id' => $user->id,
            'shop_sales_id' => $user->id,
            'payment_status' => BillPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->postJson(route('bills.pay', $bill->id))
            ->assertStatus(422);
    }
}
