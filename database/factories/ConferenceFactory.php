<?php

namespace Database\Factories;

use App\Enums\ConferenceStatus;
use App\Models\Conference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conference>
 */
class ConferenceFactory extends Factory
{
    protected $model = Conference::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().fake()->numberBetween(1, 999).'說明會',
            'status' => fake()->randomElement([ConferenceStatus::Active, ConferenceStatus::Inactive]),
            'register_started_at' => now()->subDays(7),
            'register_ended_at' => now()->subDays(1),
            'started_at' => now()->addDays(7),
            'ended_at' => now()->addDays(7)->addHours(2),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => ConferenceStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => ConferenceStatus::Inactive]);
    }
}
