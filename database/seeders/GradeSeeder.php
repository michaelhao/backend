<?php

namespace Database\Seeders;

use App\Enums\GradeStatus;
use App\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            ['name' => '版本S', 'code' => 'grade_s', 'price' => 10000, 'weight' => 100],
            ['name' => '版本A', 'code' => 'grade_a', 'price' => 9000,  'weight' => 85],
            ['name' => '版本B', 'code' => 'grade_b', 'price' => 8000,  'weight' => 70],
            ['name' => '版本C', 'code' => 'grade_c', 'price' => 7000,  'weight' => 55],
            ['name' => '版本D', 'code' => 'grade_d', 'price' => 6000,  'weight' => 40],
            ['name' => '版本E', 'code' => 'grade_e', 'price' => 5000,  'weight' => 25],
        ];

        foreach ($grades as $grade) {
            Grade::updateOrCreate(
                ['code' => $grade['code']],
                ['name' => $grade['name'], 'price' => $grade['price'], 'weight' => $grade['weight'], 'status' => GradeStatus::Active->value],
            );
        }
    }
}
