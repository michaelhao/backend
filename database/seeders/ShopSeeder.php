<?php

namespace Database\Seeders;

use App\Enums\ShopStatus;
use App\Models\Grade;
use App\Models\Shop;
use App\Models\ShopAdmin;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $shops = [
            [
                'shop' => [
                    'name'       => '正品旗艦店',
                    'email'      => 'flagship@example.com',
                    'grade_code' => 'grade_s',
                    'status'     => ShopStatus::Active->value,
                ],
                'admin' => [
                    'name'            => '王小明',
                    'email'           => 'admin@flagship.com',
                    'password'        => 'password',
                    'business_number' => '12345678',
                    'company_name'    => '正品有限公司',
                ],
            ],
            [
                'shop' => [
                    'name'       => '精選商行',
                    'email'      => 'select@example.com',
                    'grade_code' => 'grade_a',
                    'status'     => ShopStatus::Active->value,
                ],
                'admin' => [
                    'name'     => '李小華',
                    'email'    => 'admin@select.com',
                    'password' => 'password',
                ],
            ],
            [
                'shop' => [
                    'name'       => '優質商店',
                    'email'      => 'quality@example.com',
                    'grade_code' => 'grade_b',
                    'status'     => ShopStatus::Closed->value,
                ],
                'admin' => [
                    'name'     => '陳大同',
                    'email'    => 'admin@quality.com',
                    'password' => 'password',
                ],
            ],
            [
                'shop' => [
                    'name'       => '過期範例店',
                    'email'      => 'expired@example.com',
                    'grade_code' => 'grade_c',
                    'status'     => ShopStatus::Expired->value,
                ],
                'admin' => [
                    'name'     => '林小芳',
                    'email'    => 'admin@expired.com',
                    'password' => 'password',
                ],
            ],
        ];

        foreach ($shops as $data) {
            $grade = Grade::where('code', $data['shop']['grade_code'])->firstOrFail();

            $shop = Shop::updateOrCreate(
                ['email' => $data['shop']['email']],
                [
                    'name'     => $data['shop']['name'],
                    'grade_id' => $grade->id,
                    'status'   => $data['shop']['status'],
                ],
            );

            ShopAdmin::updateOrCreate(
                ['shop_id' => $shop->id],
                [
                    'name'            => $data['admin']['name'],
                    'email'           => $data['admin']['email'],
                    'password'        => $data['admin']['password'],
                    'business_number' => $data['admin']['business_number'] ?? null,
                    'company_name'    => $data['admin']['company_name'] ?? null,
                ],
            );
        }
    }
}
