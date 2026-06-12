<?php

namespace Tests\Feature;

use App\Enums\GradeStatus;
use App\Enums\ShopStatus;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopAdmin;
use App\Models\User;
use App\Support\Mask;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopRuTest extends TestCase
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

    private function createShopWithAdmin(array $shopAttrs = [], array $adminAttrs = []): Shop
    {
        $shop = Shop::factory()->create($shopAttrs);
        ShopAdmin::factory()->for($shop)->create($adminAttrs);

        return $shop->load('admin');
    }

    // ─── Index ───────────────────────────────────────────────────────────────

    public function test_admin_can_access_shop_index(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('shops.index'));

        $response->assertStatus(200);
    }

    public function test_viewer_can_access_shop_index(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $response = $this->get(route('shops.index'));

        $response->assertStatus(200);
    }

    // ─── Edit form ────────────────────────────────────────────────────────────

    public function test_admin_can_access_shop_edit(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin(['name' => '測試商店'], ['email' => 'admin@example.com']);

        $response = $this->get(route('shops.edit', $shop));

        $response->assertStatus(200);
        $response->assertSee('測試商店');
        // masked email should be visible in the display span
        $response->assertSee(Mask::email('admin@example.com'));
    }

    public function test_viewer_cannot_access_shop_edit(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');
        $shop = $this->createShopWithAdmin();

        $response = $this->get(route('shops.edit', $shop));

        $response->assertRedirect();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_shop(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();
        $shop = $this->createShopWithAdmin();

        $response = $this->put(route('shops.update', $shop), [
            'name' => '更新商店名稱',
            'email' => 'updated@shop.com',
            'grade_id' => $grade->id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => '新管理員',
                'email' => 'newadmin@shop.com',
            ],
        ]);

        $response->assertRedirect(route('shops.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'name' => '更新商店名稱',
            'email' => 'updated@shop.com',
        ]);
        $this->assertDatabaseHas('shops_admin', [
            'shop_id' => $shop->id,
            'name' => '新管理員',
        ]);
        $this->assertSame('newadmin@shop.com', $shop->admin->fresh()->email);
    }

    public function test_update_allows_same_shop_email_for_self(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin(['email' => 'same@shop.com']);

        $response = $this->put(route('shops.update', $shop), [
            'name' => $shop->name,
            'email' => 'same@shop.com',
            'grade_id' => $shop->grade_id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => $shop->admin->email,
            ],
        ]);

        $response->assertRedirect(route('shops.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_update_allows_same_admin_email_for_self(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin([], ['email' => 'sameadmin@shop.com']);

        $response = $this->put(route('shops.update', $shop), [
            'name' => $shop->name,
            'email' => $shop->email,
            'grade_id' => $shop->grade_id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => 'sameadmin@shop.com',
            ],
        ]);

        $response->assertRedirect(route('shops.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_update_fails_with_duplicate_shop_email(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        Shop::factory()->create(['email' => 'taken@shop.com']);
        $shop = $this->createShopWithAdmin();

        $response = $this->put(route('shops.update', $shop), [
            'name' => $shop->name,
            'email' => 'taken@shop.com',
            'grade_id' => $shop->grade_id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => $shop->admin->email,
            ],
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_update_fails_with_duplicate_admin_email(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $other = $this->createShopWithAdmin([], ['email' => 'takenAdmin@shop.com']);
        $shop = $this->createShopWithAdmin();

        $response = $this->put(route('shops.update', $shop), [
            'name' => $shop->name,
            'email' => $shop->email,
            'grade_id' => $shop->grade_id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => 'takenAdmin@shop.com',
            ],
        ]);

        $response->assertSessionHasErrors('admin.email');
    }

    public function test_update_fails_with_invalid_status(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();

        $response = $this->put(route('shops.update', $shop), [
            'name' => $shop->name,
            'email' => $shop->email,
            'grade_id' => $shop->grade_id,
            'status' => 99,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => $shop->admin->email,
            ],
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_update_fails_with_nonexistent_grade_id(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();

        $response = $this->put(route('shops.update', $shop), [
            'name' => $shop->name,
            'email' => $shop->email,
            'grade_id' => 99999,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => $shop->admin->email,
            ],
        ]);

        $response->assertSessionHasErrors('grade_id');
    }

    public function test_update_fails_when_assigning_inactive_grade(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();
        $inactiveGrade = Grade::factory()->create(['status' => GradeStatus::Inactive]);

        $response = $this->put(route('shops.update', $shop), [
            'name' => $shop->name,
            'email' => $shop->email,
            'grade_id' => $inactiveGrade->id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => $shop->admin->email,
            ],
        ]);

        $response->assertSessionHasErrors('grade_id');
    }

    public function test_update_allows_keeping_existing_inactive_grade(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $inactiveGrade = Grade::factory()->create(['status' => GradeStatus::Inactive]);
        $shop = $this->createShopWithAdmin(['grade_id' => $inactiveGrade->id]);

        $response = $this->put(route('shops.update', $shop), [
            'name' => '改其他欄位',
            'email' => $shop->email,
            'grade_id' => $inactiveGrade->id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => $shop->admin->email,
            ],
        ]);

        $response->assertRedirect(route('shops.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'name' => '改其他欄位']);
    }

    public function test_certification_data_saved_to_db(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();

        $response = $this->put(route('shops.update', $shop), [
            'name' => $shop->name,
            'email' => $shop->email,
            'grade_id' => $shop->grade_id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => $shop->admin->email,
                'business_number' => '12345678',
                'company_name' => '測試股份有限公司',
            ],
        ]);

        $response->assertRedirect(route('shops.index'));
        $this->assertDatabaseHas('shops_admin', [
            'shop_id' => $shop->id,
            'business_number' => '12345678',
            'company_name' => '測試股份有限公司',
        ]);
    }

    public function test_edit_nonexistent_shop_redirects_with_error(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('shops.edit', 99999));

        $response->assertRedirect(route('shops.index'));
        $response->assertSessionHas('error');
    }

    public function test_update_nonexistent_shop_redirects_with_error(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();

        $response = $this->put(route('shops.update', 99999), [
            'name' => '不存在的商店',
            'email' => 'ghost@shop.com',
            'grade_id' => $grade->id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => '管理員',
                'email' => 'ghost-admin@shop.com',
            ],
        ]);

        $response->assertRedirect(route('shops.index'));
        $response->assertSessionHas('error');
    }

    public function test_update_ignores_unvalidated_admin_fields(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();
        $otherShop = Shop::factory()->create();
        $originalPassword = $shop->admin->getRawOriginal('password');

        $response = $this->put(route('shops.update', $shop), [
            'name' => $shop->name,
            'email' => $shop->email,
            'grade_id' => $shop->grade_id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => $shop->admin->email,
                'password' => 'hacked-password',
                'shop_id' => $otherShop->id,
            ],
        ]);

        $response->assertRedirect(route('shops.index'));

        $admin = $shop->admin->fresh();
        $this->assertSame($originalPassword, $admin->getRawOriginal('password'));
        $this->assertSame($shop->id, $admin->shop_id);
    }

    public function test_update_shop_without_admin_does_not_fail(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = Shop::factory()->create();

        $response = $this->put(route('shops.update', $shop), [
            'name' => '無管理員商店',
            'email' => $shop->email,
            'grade_id' => $shop->grade_id,
            'status' => ShopStatus::Active->value,
            'admin' => [
                'name' => '管理員',
                'email' => 'no-admin@shop.com',
            ],
        ]);

        $response->assertRedirect(route('shops.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'name' => '無管理員商店']);
        $this->assertDatabaseMissing('shops_admin', ['shop_id' => $shop->id]);
    }

    public function test_per_page_form_preserves_uncertified_filter(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('shops.index', ['is_certified' => '0']));

        $response->assertStatus(200);
        $response->assertSee('name="is_certified" value="0"', false);
    }

    // ─── Certify endpoint ────────────────────────────────────────────────────

    public function test_certify_missing_business_number_returns_422(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();

        $response = $this->postJson(route('shops.certify', $shop), []);

        $response->assertStatus(422);
    }

    public function test_certify_invalid_business_number_returns_422(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();

        // Non-digit characters
        $response = $this->postJson(route('shops.certify', $shop), ['business_number' => '1234AB78']);
        $response->assertStatus(422);

        // Too short
        $response = $this->postJson(route('shops.certify', $shop), ['business_number' => '1234567']);
        $response->assertStatus(422);

        // Too long
        $response = $this->postJson(route('shops.certify', $shop), ['business_number' => '123456789']);
        $response->assertStatus(422);
    }

    public function test_certify_success_returns_company_name(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();

        Http::fake([
            '*' => Http::response([
                ['Company_Name' => '測試公司'],
            ], 200),
        ]);

        $response = $this->postJson(route('shops.certify', $shop), ['business_number' => '12345678']);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'company_name' => '測試公司']);
    }

    public function test_certify_empty_response_returns_failure(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();

        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $response = $this->postJson(route('shops.certify', $shop), ['business_number' => '12345678']);

        $response->assertStatus(200);
        $response->assertJson(['success' => false]);
    }

    public function test_certify_connection_exception_returns_failure(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();

        Http::fake([
            '*' => function () {
                throw new ConnectionException('timeout');
            },
        ]);

        $response = $this->postJson(route('shops.certify', $shop), ['business_number' => '12345678']);

        $response->assertStatus(200);
        $response->assertJson(['success' => false]);
    }

    public function test_certify_nonexistent_shop_returns_404_json(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->postJson(route('shops.certify', 99999), ['business_number' => '12345678']);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    // ─── Non-existent routes ─────────────────────────────────────────────────

    public function test_store_route_does_not_exist(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post('/shops', []);

        $response->assertStatus(405);
    }

    public function test_delete_route_does_not_exist(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $shop = $this->createShopWithAdmin();

        $response = $this->delete("/shops/{$shop->id}");

        $response->assertStatus(405);
    }
}
