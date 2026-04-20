<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Shop;
use App\Repositories\ShopAddonBalanceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopAddonBalanceTest extends TestCase
{
    use RefreshDatabase;

    private ShopAddonBalanceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ShopAddonBalanceRepository::class);
    }

    public function test_get_available_quantity_sums_only_non_expired_balances(): void
    {
        $shop = Shop::factory()->create();
        $addon = Addon::factory()->create();

        DB::table('shop_addon_balances')->insert([
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'quantity' => 5,
            'expired_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('shop_addon_balances')->insert([
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'quantity' => 3,
            'expired_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertEquals(3, $this->repository->getAvailableQuantity($shop->id, $addon->id));
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
}
