<?php

namespace Database\Factories;

use App\Models\ShopAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopAdmin>
 */
class ShopAdminFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => mb_substr(fake()->name(), 0, 20),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'business_number' => null,
            'company_name' => null,
        ];
    }
}
