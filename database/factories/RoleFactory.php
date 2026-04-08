<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * 'default_route' references 'Dashboard.index' which must exist in the permissions table.
     * Tests using this factory should call $this->seed(PermissionSeeder::class) first.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'default_route' => 'Dashboard.index',
        ];
    }
}
