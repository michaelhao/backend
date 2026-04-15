<?php

namespace Database\Seeders;

use App\Enums\AddonStatus;
use App\Enums\AddonSyncing;
use App\Enums\AddonType;
use App\Models\Addon;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    public function run(): void
    {
        $addons = [
            ['name' => '功能1', 'type' => AddonType::Feature->value, 'price' => 8000, 'unit' => null, 'status' => AddonStatus::Active->value, 'syncing' => AddonSyncing::Done->value],
            ['name' => '功能2', 'type' => AddonType::Feature->value, 'price' => 7000, 'unit' => null, 'status' => AddonStatus::Active->value, 'syncing' => AddonSyncing::Done->value],
            ['name' => '功能3', 'type' => AddonType::Feature->value, 'price' => 6000, 'unit' => null, 'status' => AddonStatus::Active->value, 'syncing' => AddonSyncing::Done->value],
            ['name' => '功能4', 'type' => AddonType::Feature->value, 'price' => 5000, 'unit' => null, 'status' => AddonStatus::Active->value, 'syncing' => AddonSyncing::Done->value],
            ['name' => '功能5', 'type' => AddonType::Feature->value, 'price' => 4000, 'unit' => null, 'status' => AddonStatus::Active->value, 'syncing' => AddonSyncing::Done->value],
        ];

        foreach ($addons as $data) {
            Addon::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}
