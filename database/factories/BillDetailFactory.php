<?php

namespace Database\Factories;

use App\Enums\BillDetailType;
use App\Models\Bill;
use App\Models\BillDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillDetailFactory extends Factory
{
    protected $model = BillDetail::class;

    public function definition(): array
    {
        $startAt = now()->addDay();

        return [
            'bill_id' => Bill::factory(),
            'type' => BillDetailType::Grades,
            'payment_type' => 1,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'name' => fake()->word(),
            'start_at' => $startAt,
            'expired_at' => $startAt->copy()->addMonths(1)->endOfMonth()->setTime(23, 59, 59),
            'total_months' => 1,
            'is_effective' => 1,
            'canceled_at' => null,
            'canceled_by' => null,
            'applied_at' => null,
            'memo' => null,
        ];
    }
}
