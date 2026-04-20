<?php

namespace Tests\Feature;

use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use App\Models\Addon;
use App\Models\Shop;
use App\Repositories\ShopAddonBalanceRepository;
use App\Services\ShopAddonPurchaseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopAddonBalanceTest extends TestCase
{
    use RefreshDatabase;

    private ShopAddonPurchaseService $service;

    private ShopAddonBalanceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ShopAddonBalanceRepository::class);
        $this->service = app(ShopAddonPurchaseService::class);
    }

    public function test_first_purchase_creates_shop_addon_and_balance(): void
    {
        $shop = Shop::factory()->create();
        $addon = Addon::factory()->create();
        $expiredAt = Carbon::parse('2026-06-30 23:59:59');

        $this->service->purchase($shop->id, $addon->id, 3, $expiredAt);

        $this->assertDatabaseHas('shops_addons', [
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'source' => ShopAddonSource::Purchased->value,
            'status' => ShopAddonStatus::Enabled->value,
            'expired_at' => $expiredAt->format('Y-m-d H:i:s'),
        ]);

        $this->assertDatabaseHas('shop_addon_balances', [
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'quantity' => 3,
            'expired_at' => $expiredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_repeat_purchase_updates_shop_addon_expired_at_and_adds_new_balance(): void
    {
        $shop = Shop::factory()->create();
        $addon = Addon::factory()->create();
        $firstExpiry = Carbon::parse('2026-02-28 23:59:59');
        $secondExpiry = Carbon::parse('2026-04-30 23:59:59');

        $this->service->purchase($shop->id, $addon->id, 1, $firstExpiry);
        $this->service->purchase($shop->id, $addon->id, 1, $secondExpiry);

        // shops_addons should still have only one row, with updated expired_at
        $this->assertEquals(1, DB::table('shops_addons')
            ->where('shop_id', $shop->id)
            ->where('addon_id', $addon->id)
            ->count());

        $this->assertDatabaseHas('shops_addons', [
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'expired_at' => $secondExpiry->format('Y-m-d H:i:s'),
        ]);

        // shop_addon_balances should have two independent rows
        $this->assertEquals(2, DB::table('shop_addon_balances')
            ->where('shop_id', $shop->id)
            ->where('addon_id', $addon->id)
            ->count());

        $this->assertDatabaseHas('shop_addon_balances', [
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'quantity' => 1,
            'expired_at' => $firstExpiry->format('Y-m-d H:i:s'),
        ]);

        $this->assertDatabaseHas('shop_addon_balances', [
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'quantity' => 1,
            'expired_at' => $secondExpiry->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_get_available_quantity_sums_only_non_expired_balances(): void
    {
        $shop = Shop::factory()->create();
        $addon = Addon::factory()->create();

        // expired balance
        DB::table('shop_addon_balances')->insert([
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'quantity' => 5,
            'expired_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // valid balance
        DB::table('shop_addon_balances')->insert([
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'quantity' => 3,
            'expired_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $available = $this->repository->getAvailableQuantity($shop->id, $addon->id);

        $this->assertEquals(3, $available);
    }

    public function test_get_available_quantity_returns_zero_when_all_expired(): void
    {
        $shop = Shop::factory()->create();
        $addon = Addon::factory()->create();

        DB::table('shop_addon_balances')->insert([
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'quantity' => 10,
            'expired_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertEquals(0, $this->repository->getAvailableQuantity($shop->id, $addon->id));
    }

    public function test_transaction_rollback_leaves_no_orphan_records(): void
    {
        $shop = Shop::factory()->create();
        $addon = Addon::factory()->create();
        $expiredAt = Carbon::parse('2026-06-30 23:59:59');

        // Force a failure mid-transaction by making the balance insert fail (invalid FK)
        try {
            DB::transaction(function () use ($shop, $addon, $expiredAt) {
                DB::table('shops_addons')->insert([
                    'shop_id' => $shop->id,
                    'addon_id' => $addon->id,
                    'source' => ShopAddonSource::Purchased->value,
                    'status' => ShopAddonStatus::Enabled->value,
                    'expired_at' => $expiredAt->format('Y-m-d H:i:s'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Intentionally fail with non-existent shop_id
                DB::table('shop_addon_balances')->insert([
                    'shop_id' => 99999,
                    'addon_id' => $addon->id,
                    'quantity' => 1,
                    'expired_at' => $expiredAt->format('Y-m-d H:i:s'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable) {
            // expected
        }

        $this->assertDatabaseMissing('shops_addons', ['shop_id' => $shop->id, 'addon_id' => $addon->id]);
        $this->assertDatabaseMissing('shop_addon_balances', ['shop_id' => $shop->id, 'addon_id' => $addon->id]);
    }
}
