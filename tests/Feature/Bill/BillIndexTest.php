<?php

namespace Tests\Feature\Bill;

use App\Enums\BillPaymentMethod;
use App\Enums\BillPaymentStatus;
use App\Models\Bill;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillIndexTest extends TestCase
{
    use RefreshDatabase;

    private function seedPermissions(): void
    {
        $this->seed(PermissionSeeder::class);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);
        $user->loadPermissionsToSession();

        return $user;
    }

    private function makeBill(array $attrs = []): Bill
    {
        return Bill::factory()->create(array_merge([
            'shop_id' => Shop::factory()->create()->id,
        ], $attrs));
    }

    // ─── 權限與登入 ──────────────────────────────────────────────────────────

    public function test_admin_can_access_bill_index(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $this->get(route('bills.index'))->assertStatus(200);
    }

    public function test_viewer_can_access_bill_index(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $this->get(route('bills.index'))->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('bills.index'))->assertRedirect(route('login'));
    }

    public function test_viewer_cannot_access_bill_create(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $this->get(route('bills.create'))->assertStatus(302);
    }

    public function test_viewer_cannot_writeoff(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');
        $bill = $this->makeBill();

        $this->post(route('bills.writeoff', $bill->id), ['detail_ids' => [1]])
            ->assertStatus(302);

        $this->assertEquals(BillPaymentStatus::Pending, $bill->fresh()->payment_status);
    }

    // ─── 搜尋篩選 ────────────────────────────────────────────────────────────

    public function test_index_filters_by_no_partial_match(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $target = $this->makeBill(['no' => 'b20260101000000aaaa1111']);
        $other = $this->makeBill(['no' => 'b20260202000000bbbb2222']);

        $response = $this->get(route('bills.index', ['no' => 'aaaa']));

        $response->assertStatus(200)
            ->assertSee($target->no)
            ->assertDontSee($other->no);
    }

    public function test_index_filters_by_payment_status(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $pending = $this->makeBill(['payment_status' => BillPaymentStatus::Pending]);
        $paid = $this->makeBill(['payment_status' => BillPaymentStatus::Paid, 'paid_at' => now()]);

        $response = $this->get(route('bills.index', ['payment_status' => BillPaymentStatus::Paid->value]));

        $response->assertStatus(200)
            ->assertSee($paid->no)
            ->assertDontSee($pending->no);
    }

    public function test_index_filters_by_payment_method(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $cash = $this->makeBill(['payment_method' => BillPaymentMethod::Cash]);
        $card = $this->makeBill(['payment_method' => BillPaymentMethod::CreditCard]);

        $response = $this->get(route('bills.index', ['payment_method' => BillPaymentMethod::Cash->value]));

        $response->assertStatus(200)
            ->assertSee($cash->no)
            ->assertDontSee($card->no);
    }

    public function test_index_filters_by_sales_id(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $salesA = User::factory()->create();
        $salesB = User::factory()->create();
        $billA = $this->makeBill(['shop_sales_id' => $salesA->id]);
        $billB = $this->makeBill(['shop_sales_id' => $salesB->id]);

        $response = $this->get(route('bills.index', ['sales_id' => $salesA->id]));

        $response->assertStatus(200)
            ->assertSee($billA->no)
            ->assertDontSee($billB->no);
    }

    public function test_index_pagination_preserves_filters(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        Bill::factory()->count(21)->create([
            'shop_id' => Shop::factory()->create()->id,
            'payment_status' => BillPaymentStatus::Pending,
        ]);

        $response = $this->get(route('bills.index', ['payment_status' => BillPaymentStatus::Pending->value]));

        $response->assertStatus(200)
            ->assertSee('payment_status=1', false)
            ->assertSee('page=2', false);
    }
}
