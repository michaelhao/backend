<?php

namespace Tests\Feature;

use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use App\Jobs\SyncShopAddonsForGrade;
use App\Models\Addon;
use App\Models\Grade;
use App\Models\Shop;
use App\Models\ShopAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncShopAddonsForGradeTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_job_adds_new_addon_to_shops(): void
    {
        $grade = Grade::factory()->create();
        $addon = Addon::factory()->create();
        $shop = $this->createShopWithAdmin($grade);

        // addon now belongs to grade
        $this->attachAddonToGrade($grade, $addon);
        // shop has no shops_addons row yet

        (new SyncShopAddonsForGrade($grade))->handle();

        $this->assertDatabaseHas('shops_addons', [
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'source' => ShopAddonSource::Grade->value,
        ]);
    }

    public function test_job_demotes_removed_addon(): void
    {
        $grade = Grade::factory()->create();
        $addon = Addon::factory()->create();
        $shop = $this->createShopWithAdmin($grade);

        // shop has addon as Grade source, but it's no longer in grades_addons
        $this->attachAddonToShop($shop, $addon, ShopAddonSource::Grade->value);

        (new SyncShopAddonsForGrade($grade))->handle();

        $row = DB::table('shops_addons')
            ->where('shop_id', $shop->id)
            ->where('addon_id', $addon->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals(ShopAddonSource::Purchased->value, $row->source);
        $this->assertNotNull($row->expired_at);
        $this->assertStringContainsString('23:59:59', $row->expired_at);
    }

    public function test_job_upgrades_purchased_addon_when_added_to_grade(): void
    {
        $grade = Grade::factory()->create();
        $addon = Addon::factory()->create();
        $shop = $this->createShopWithAdmin($grade);

        // addon now in grades_addons
        $this->attachAddonToGrade($grade, $addon);
        // shop has it as Purchased
        $this->attachAddonToShop($shop, $addon, ShopAddonSource::Purchased->value, now()->addDays(5)->format('Y-m-d H:i:s'));

        (new SyncShopAddonsForGrade($grade))->handle();

        $row = DB::table('shops_addons')
            ->where('shop_id', $shop->id)
            ->where('addon_id', $addon->id)
            ->first();

        $this->assertEquals(ShopAddonSource::Grade->value, $row->source);
        $this->assertNull($row->expired_at);
    }

    public function test_job_handles_grade_with_no_shops(): void
    {
        $grade = Grade::factory()->create();
        $addon = Addon::factory()->create();
        $this->attachAddonToGrade($grade, $addon);

        // no shops under this grade
        (new SyncShopAddonsForGrade($grade))->handle();

        $this->assertDatabaseCount('shops_addons', 0);
    }

    public function test_job_leaves_purchased_addons_not_in_grade_untouched(): void
    {
        $grade = Grade::factory()->create();
        $addonInGrade = Addon::factory()->create();
        $addonOwned = Addon::factory()->create();
        $shop = $this->createShopWithAdmin($grade);

        $this->attachAddonToGrade($grade, $addonInGrade);
        $this->attachAddonToShop($shop, $addonOwned, ShopAddonSource::Purchased->value, now()->addDays(30)->format('Y-m-d H:i:s'));

        (new SyncShopAddonsForGrade($grade))->handle();

        $row = DB::table('shops_addons')
            ->where('shop_id', $shop->id)
            ->where('addon_id', $addonOwned->id)
            ->first();

        $this->assertEquals(ShopAddonSource::Purchased->value, $row->source);
        $this->assertNotNull($row->expired_at);
    }
}
