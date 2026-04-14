<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShopAdmin>
 */
class ShopAdminFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'            => fake()->name(),
            'email'           => fake()->unique()->safeEmail(),
            'password'        => 'password',
            'business_number' => null,
            'company_name'    => null,
        ];
    }
}
