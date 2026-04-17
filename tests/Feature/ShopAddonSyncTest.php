<?php

namespace Tests\Feature;

use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use App\Models\Addon;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopAdmin;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopAddonSyncTest extends TestCase
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

    private function createShopWithAdmin(Grade $grade): Shop
    {
        $shop = Shop::factory()->create(['grade_id' => $grade->id]);
        ShopAdmin::factory()->create(['shop_id' => $shop->id]);

        return $shop;
    }

    private function attachAddonToGrade(Grade $grade, Addon $addon): void
    {
        DB::table('grades_addons')->insert([
            'grade_id' => $grade->id,
            'addon_id' => $addon->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachAddonToShop(Shop $shop, Addon $addon, int $source, ?string $expiredAt = null): void
    {
        DB::table('shops_addons')->insert([
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'source' => $source,
            'status' => ShopAddonStatus::Enabled->value,
            'expired_at' => $expiredAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function shopUpdatePayload(Shop $shop, array $overrides = []): array
    {
        $shop->load('admin');

        return array_merge([
            'name' => $shop->name,
            'email' => $shop->email,
            'grade_id' => $shop->grade_id,
            'status' => $shop->status->value,
            'admin' => [
                'name' => $shop->admin->name,
                'email' => $shop->admin->email,
            ],
        ], $overrides);
    }

    public function test_grade_change_removes_old_grade_addons(): void
    {
        $this->seedPermissions();
        $this->createAdminUser();

        $gradeA = Grade::factory()->create();
        $gradeB = Grade::factory()->create();
        $addon = Addon::factory()->create();
        $shop = $this->createShopWithAdmin($gradeA);

        // addon belongs to gradeA
        $this->attachAddonToGrade($gradeA, $addon);
        // shop has this addon as source=Grade
        $this->attachAddonToShop($shop, $addon, ShopAddonSource::Grade->value);

        // update shop to gradeB (which has no addons)
        $this->put(route('shops.update', $shop), $this->shopUpdatePayload($shop, ['grade_id' => $gradeB->id]));

        $row = DB::table('shops_addons')
            ->where('shop_id', $shop->id)
            ->where('addon_id', $addon->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals(ShopAddonSource::Purchased->value, $row->source);
        $this->assertNotNull($row->expired_at);
        // expired_at should be today at 23:59:59
        $this->assertStringContainsString(now()->toDateString(), $row->expired_at);
        $this->assertStringContainsString('23:59:59', $row->expired_at);
    }

    public function test_grade_change_adds_new_grade_addons(): void
    {
        $this->seedPermissions();
        $this->createAdminUser();

        $gradeA = Grade::factory()->create();
        $gradeB = Grade::factory()->create();
        $addon = Addon::factory()->create();
        $shop = $this->createShopWithAdmin($gradeA);

        // addon belongs to gradeB
        $this->attachAddonToGrade($gradeB, $addon);

        // update shop to gradeB
        $this->put(route('shops.update', $shop), $this->shopUpdatePayload($shop, ['grade_id' => $gradeB->id]));

        $this->assertDatabaseHas('shops_addons', [
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'source' => ShopAddonSource::Grade->value,
        ]);
    }

    public function test_grade_change_upgrades_purchased_addon_to_grade(): void
    {
        $this->seedPermissions();
        $this->createAdminUser();

        $gradeA = Grade::factory()->create();
        $gradeB = Grade::factory()->create();
        $addon = Addon::factory()->create();
        $shop = $this->createShopWithAdmin($gradeA);

        // addon belongs to gradeB
        $this->attachAddonToGrade($gradeB, $addon);
        // shop already has this addon as Purchased (source=2)
        $this->attachAddonToShop($shop, $addon, ShopAddonSource::Purchased->value, now()->addDays(10)->format('Y-m-d H:i:s'));

        // update shop to gradeB
        $this->put(route('shops.update', $shop), $this->shopUpdatePayload($shop, ['grade_id' => $gradeB->id]));

        $row = DB::table('shops_addons')
            ->where('shop_id', $shop->id)
            ->where('addon_id', $addon->id)
            ->first();

        $this->assertEquals(ShopAddonSource::Grade->value, $row->source);
        $this->assertNull($row->expired_at);
    }

    public function test_grade_change_leaves_other_purchased_addons_untouched(): void
    {
        $this->seedPermissions();
        $this->createAdminUser();

        $gradeA = Grade::factory()->create();
        $gradeB = Grade::factory()->create();
        $addonInA = Addon::factory()->create();
        $addonOwned = Addon::factory()->create();
        $shop = $this->createShopWithAdmin($gradeA);

        // addonInA belongs to gradeA
        $this->attachAddonToGrade($gradeA, $addonInA);
        // shop has addonInA as Grade source
        $this->attachAddonToShop($shop, $addonInA, ShopAddonSource::Grade->value);
        // shop also independently purchased addonOwned (source=2)
        $ownedExpiry = now()->addDays(30)->format('Y-m-d H:i:s');
        $this->attachAddonToShop($shop, $addonOwned, ShopAddonSource::Purchased->value, $ownedExpiry);

        // update shop to gradeB (which has neither addon)
        $this->put(route('shops.update', $shop), $this->shopUpdatePayload($shop, ['grade_id' => $gradeB->id]));

        // addonOwned should remain unchanged (source=2, same expired_at)
        $row = DB::table('shops_addons')
            ->where('shop_id', $shop->id)
            ->where('addon_id', $addonOwned->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals(ShopAddonSource::Purchased->value, $row->source);
        $this->assertEquals($ownedExpiry, $row->expired_at);
    }

    public function test_shop_update_without_grade_change_does_not_touch_addons(): void
    {
        $this->seedPermissions();
        $this->createAdminUser();

        $grade = Grade::factory()->create();
        $addon = Addon::factory()->create();
        $shop = $this->createShopWithAdmin($grade);
        $this->attachAddonToShop($shop, $addon, ShopAddonSource::Grade->value);

        $originalRow = DB::table('shops_addons')
            ->where('shop_id', $shop->id)
            ->where('addon_id', $addon->id)
            ->first();

        // update name only, grade_id stays the same
        $this->put(route('shops.update', $shop), $this->shopUpdatePayload($shop, ['name' => '新名稱']));

        $newRow = DB::table('shops_addons')
            ->where('shop_id', $shop->id)
            ->where('addon_id', $addon->id)
            ->first();

        $this->assertEquals($originalRow->source, $newRow->source);
        $this->assertEquals($originalRow->expired_at, $newRow->expired_at);
    }
}
