<?php

namespace Database\Factories;

use App\Enums\GradeStatus;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'code' => 'grade_'.fake()->unique()->lexify('???'),
            'name' => '版本'.fake()->unique()->lexify('???'),
            'price' => fake()->numberBetween(2, 99999),
            'status' => GradeStatus::Active,
        ];
    }
}
