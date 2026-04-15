<?php

namespace Database\Factories;

use App\Enums\AddonStatus;
use App\Enums\AddonSyncing;
use App\Enums\AddonType;
use App\Models\Addon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    protected $model = Addon::class;

    public function definition(): array
    {
        return [
            'type' => AddonType::Feature,
            'name' => fake()->unique()->word().fake()->numberBetween(1, 999),
            'price' => fake()->numberBetween(100, 10000),
            'unit' => null,
            'status' => AddonStatus::Active,
            'syncing' => AddonSyncing::Done,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => AddonStatus::Inactive]);
    }

    public function deleted(): static
    {
        return $this->state(['status' => AddonStatus::Deleted]);
    }
}
