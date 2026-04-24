<?php

namespace Database\Factories;

use App\Enums\BillPaymentStatus;
use App\Models\Bill;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'no' => 'b' . now()->format('YmdHis') . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'creator_id' => User::factory(),
            'shop_id' => Shop::factory(),
            'shop_sales_id' => User::factory(),
            'total' => 0,
            'total_grade' => 0,
            'total_addons' => 0,
            'discount_amount' => null,
            'payment_status' => BillPaymentStatus::Pending,
            'payment_method' => null,
            'paid_at' => null,
            'invoice_no' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['payment_status' => BillPaymentStatus::Pending]);
    }

    public function unpaid(): static
    {
        return $this->state(['payment_status' => BillPaymentStatus::Unpaid]);
    }

    public function paid(): static
    {
        return $this->state([
            'payment_status' => BillPaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
