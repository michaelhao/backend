<?php

namespace Database\Factories;

use App\Enums\ShopStatus;
use App\Models\Grade;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'grade_id' => Grade::factory(),
            'status' => ShopStatus::Active,
        ];
    }
}
