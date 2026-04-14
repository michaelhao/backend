<?php

namespace Database\Factories;

use App\Enums\ShopStatus;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shop>
 */
class ShopFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'     => fake()->company(),
            'email'    => fake()->unique()->safeEmail(),
            'grade_id' => Grade::factory(),
            'status'   => ShopStatus::Active,
        ];
    }
}
