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
            ['name' => '版本S', 'code' => 'grade_s', 'price' => 10000],
            ['name' => '版本A', 'code' => 'grade_a', 'price' => 9000],
            ['name' => '版本B', 'code' => 'grade_b', 'price' => 8000],
            ['name' => '版本C', 'code' => 'grade_c', 'price' => 7000],
            ['name' => '版本D', 'code' => 'grade_d', 'price' => 6000],
            ['name' => '版本E', 'code' => 'grade_e', 'price' => 5000],
        ];

        foreach ($grades as $grade) {
            Grade::updateOrCreate(
                ['code' => $grade['code']],
                ['name' => $grade['name'], 'price' => $grade['price'], 'status' => GradeStatus::Active->value],
            );
        }
    }
}
