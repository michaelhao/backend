<?php

namespace Database\Seeders;

use App\Models\BillDiscount;
use Illuminate\Database\Seeder;

class BillDiscountSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => '帳戶餘額',     'description' => '從帳戶餘額扣抵'],
            ['name' => '優惠券',       'description' => '使用優惠券折抵'],
            ['name' => '行銷活動',     'description' => '行銷活動優惠折抵'],
            ['name' => '人工調整折抵', 'description' => '由業務人工調整的折抵金額'],
        ];

        foreach ($items as $item) {
            BillDiscount::updateOrCreate(['name' => $item['name']], $item);
        }
    }
}
