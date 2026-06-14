<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopAdmin;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-05-09 10:00:00', 'Asia/Taipei'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param string[] $permissionNames */
    private function createUserWithCustomRole(array $permissionNames, string $defaultRoute): User
    {
        $permissions = Permission::whereIn('name', $permissionNames)->get();
        $role = Role::create([
            'name' => 'TestRole_'.Str::random(6),
            'description' => 'Test role',
            'default_route' => $defaultRoute,
        ]);
        $role->permissions()->sync($permissions->pluck('id'));

        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);
        $user->loadPermissionsToSession();

        return $user;
    }

    // ── 6.1 存取權限 ───────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->createUserWithRole('Admin');

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
    }

    public function test_user_without_dashboard_permission_is_redirected_to_default_route(): void
    {
        $this->createUserWithCustomRole(['Shop.index'], 'Shop.index');

        $response = $this->get('/');

        $response->assertRedirect(route('shops.index'));
    }

    // ── 6.2 今日新增商店 ────────────────────────────────────────────────────

    public function test_only_my_new_shops_are_shown(): void
    {
        $userA = $this->createUserWithRole('Admin');
        $userB = User::factory()->create();

        Shop::factory()->count(2)->create([
            'sales_id' => $userA->id,
            'created_at' => Carbon::parse('2026-05-09 09:00:00', 'Asia/Taipei'),
        ]);
        Shop::factory()->create([
            'sales_id' => $userB->id,
            'created_at' => Carbon::parse('2026-05-09 09:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $this->assertCount(2, $response->viewData('overview')['new_shops']);
    }

    public function test_yesterday_shops_are_not_included(): void
    {
        $user = $this->createUserWithRole('Admin');

        Shop::factory()->create([
            'sales_id' => $user->id,
            'created_at' => Carbon::parse('2026-05-09 09:00:00', 'Asia/Taipei'),
        ]);
        Shop::factory()->create([
            'sales_id' => $user->id,
            'created_at' => Carbon::parse('2026-05-08 23:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $this->assertCount(1, $response->viewData('overview')['new_shops']);
    }

    public function test_today_boundary_uses_asia_taipei_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-09 02:00:00', 'Asia/Taipei'));

        $user = $this->createUserWithRole('Admin');
        Shop::factory()->create([
            'sales_id' => $user->id,
            'created_at' => Carbon::parse('2026-05-09 01:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $this->assertCount(1, $response->viewData('overview')['new_shops']);
    }

    public function test_null_admin_renders_em_dash(): void
    {
        $user = $this->createUserWithRole('Admin');
        Shop::factory()->create([
            'sales_id' => $user->id,
            'created_at' => Carbon::parse('2026-05-09 09:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $response->assertSee('—');
    }

    public function test_admin_contact_name_is_shown(): void
    {
        $user = $this->createUserWithRole('Admin');
        $shop = Shop::factory()->create([
            'sales_id' => $user->id,
            'created_at' => Carbon::parse('2026-05-09 09:00:00', 'Asia/Taipei'),
        ]);
        ShopAdmin::factory()->create(['shop_id' => $shop->id, 'name' => '陳小明']);

        $response = $this->get('/');

        $response->assertSee('陳小明');
    }

    // ── 6.3 全公司今日說明會 ─────────────────────────────────────────────────

    public function test_all_todays_conferences_are_shown_regardless_of_user(): void
    {
        $this->createUserWithRole('Admin');
        Conference::factory()->count(2)->create([
            'started_at' => Carbon::parse('2026-05-09 14:00:00', 'Asia/Taipei'),
            'ended_at' => Carbon::parse('2026-05-09 16:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $this->assertCount(2, $response->viewData('overview')['today_conferences']);
    }

    public function test_tomorrow_conference_is_not_shown(): void
    {
        $this->createUserWithRole('Admin');
        Conference::factory()->create([
            'started_at' => Carbon::parse('2026-05-09 14:00:00', 'Asia/Taipei'),
            'ended_at' => Carbon::parse('2026-05-09 16:00:00', 'Asia/Taipei'),
        ]);
        Conference::factory()->create([
            'started_at' => Carbon::parse('2026-05-10 14:00:00', 'Asia/Taipei'),
            'ended_at' => Carbon::parse('2026-05-10 16:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $this->assertCount(1, $response->viewData('overview')['today_conferences']);
    }

    public function test_title_shows_all_company_label(): void
    {
        $this->createUserWithRole('Admin');

        $response = $this->get('/');

        $response->assertSee('全公司今日說明會');
        $response->assertDontSee('我的今日說明會');
    }

    // ── 6.4 即將到期商店 ────────────────────────────────────────────────────

    public function test_only_my_expiring_shops_are_shown(): void
    {
        $userA = $this->createUserWithRole('Admin');
        $userB = User::factory()->create();

        Shop::factory()->create([
            'sales_id' => $userA->id,
            'expired_at' => Carbon::parse('2026-06-08 12:00:00', 'Asia/Taipei'),
        ]);
        Shop::factory()->create([
            'sales_id' => $userB->id,
            'expired_at' => Carbon::parse('2026-06-08 12:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $this->assertCount(1, $response->viewData('overview')['expiring_shops']);
    }

    public function test_expired_shop_is_not_included(): void
    {
        $user = $this->createUserWithRole('Admin');
        Shop::factory()->create([
            'sales_id' => $user->id,
            'expired_at' => Carbon::parse('2026-05-08 12:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $this->assertCount(0, $response->viewData('overview')['expiring_shops']);
    }

    public function test_shop_beyond_six_months_is_not_included(): void
    {
        $user = $this->createUserWithRole('Admin');
        Shop::factory()->create([
            'sales_id' => $user->id,
            'expired_at' => Carbon::parse('2026-12-31 12:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $this->assertCount(0, $response->viewData('overview')['expiring_shops']);
    }

    public function test_shop_at_exactly_six_month_endpoint_is_included(): void
    {
        $user = $this->createUserWithRole('Admin');
        Shop::factory()->create([
            'sales_id' => $user->id,
            'expired_at' => Carbon::parse('2026-11-09 23:59:59', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $this->assertCount(1, $response->viewData('overview')['expiring_shops']);
    }

    // ── 6.5 剩餘天數 + 色階 ──────────────────────────────────────────────────

    #[DataProvider('daysColorProvider')]
    public function test_days_and_color_calculation(string $expiredAt, int $expectedDays, string $expectedColor): void
    {
        $user = $this->createUserWithRole('Admin');
        Shop::factory()->create([
            'sales_id' => $user->id,
            'expired_at' => Carbon::parse($expiredAt, 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $expiringShops = $response->viewData('overview')['expiring_shops'];
        $this->assertCount(1, $expiringShops);
        $this->assertSame($expectedDays, $expiringShops[0]['days']);
        $this->assertSame($expectedColor, $expiringShops[0]['color']);
    }

    /** @return array<string, array{string, int, string}> */
    public static function daysColorProvider(): array
    {
        return [
            '30 天 → 紅' => ['2026-06-08 12:00:00', 30, '#ef4444'],
            '60 天 → 紅' => ['2026-07-08 12:00:00', 60, '#ef4444'],
            '75 天 → 橘' => ['2026-07-23 12:00:00', 75, '#f97316'],
            '90 天 → 橘' => ['2026-08-07 12:00:00', 90, '#f97316'],
            '150 天 → 黃' => ['2026-10-06 12:00:00', 150, '#ca8a04'],
        ];
    }

    // ── 6.6 Row 跳轉與權限 gate ───────────────────────────────────────────────

    public function test_shop_row_is_link_when_user_has_shop_update_permission(): void
    {
        $user = $this->createUserWithRole('Admin');
        $shop = Shop::factory()->create(['sales_id' => $user->id]);

        $response = $this->get('/');

        $response->assertSee(route('shops.edit', $shop->id), false);
    }

    public function test_shop_row_is_span_when_user_lacks_shop_update_permission(): void
    {
        $user = $this->createUserWithRole('Viewer');
        $shop = Shop::factory()->create(['sales_id' => $user->id]);

        $response = $this->get('/');

        $response->assertDontSee(route('shops.edit', $shop->id), false);
    }

    public function test_conference_row_is_link_when_user_has_conference_update_permission(): void
    {
        $this->createUserWithRole('Admin');
        $conference = Conference::factory()->create([
            'started_at' => Carbon::parse('2026-05-09 14:00:00', 'Asia/Taipei'),
            'ended_at' => Carbon::parse('2026-05-09 16:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $response->assertSee(route('conferences.edit', $conference->id), false);
    }

    public function test_conference_row_is_span_when_user_lacks_conference_update_permission(): void
    {
        $user = $this->createUserWithRole('Viewer');
        $conference = Conference::factory()->create([
            'started_at' => Carbon::parse('2026-05-09 14:00:00', 'Asia/Taipei'),
            'ended_at' => Carbon::parse('2026-05-09 16:00:00', 'Asia/Taipei'),
        ]);

        $response = $this->get('/');

        $response->assertDontSee(route('conferences.edit', $conference->id), false);
    }

    // ── 6.7 預設展開狀態 ─────────────────────────────────────────────────────

    public function test_first_two_panels_are_open_by_default(): void
    {
        $this->createUserWithRole('Admin');

        $response = $this->get('/');

        $this->assertSame(2, substr_count($response->content(), '<details open'));
    }

    public function test_expiring_shops_panel_is_closed_by_default(): void
    {
        $this->createUserWithRole('Admin');

        $response = $this->get('/');

        $content = $response->content();
        // 第三個 <details> 不含 open 屬性
        $this->assertSame(1, substr_count($content, '<details class='));
    }

    // ── 6.8 Empty state ───────────────────────────────────────────────────────

    public function test_empty_state_messages_are_shown_when_no_data(): void
    {
        $this->createUserWithRole('Admin');

        $response = $this->get('/');

        $response->assertSee('今日無新增負責商店');
        $response->assertSee('今日無說明會');
        $response->assertSee('暫無半年內到期的負責商店');
    }

    // ── 6.9 PermissionSeeder 移除 Dashboard.detail ──────────────────────────

    public function test_permission_seeder_removes_dashboard_detail(): void
    {
        // 模擬舊版 seeder 留下的 Dashboard.detail
        Permission::create([
            'name' => 'Dashboard.detail',
            'module' => 'Dashboard',
            'action' => 'detail',
            'description' => '儀表板 - 詳細頁',
        ]);

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);

        $this->assertFalse(Permission::where('name', 'Dashboard.detail')->exists());
    }

    public function test_permission_seeder_preserves_dashboard_index(): void
    {
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);

        $this->assertTrue(Permission::where('name', 'Dashboard.index')->exists());
    }
}
