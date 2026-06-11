<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = ucfirst(fake()->unique()->word());
        $action = fake()->word();

        return [
            'name' => "{$module}.{$action}",
            'module' => $module,
            'action' => $action,
            'description' => fake()->sentence(),
        ];
    }
}
