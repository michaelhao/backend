<?php

namespace App\Services;

use App\Enums\ShopStatus;
use App\Models\Grade;
use App\Models\Shop;
use App\Models\ShopAdmin;
use App\Repositories\ShopRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ShopService
{
    public function __construct(private ShopRepository $shopRepository) {}

    /**
     * @return array{shops: LengthAwarePaginator, filters: array, perPage: int, grades: \Illuminate\Database\Eloquent\Collection}
     */
    public function getIndexData(Request $request): array
    {
        $perPage = in_array((int) $request->per_page, [50, 100, 150, 200])
            ? (int) $request->per_page
            : 50;

        $filters = $request->only(['keyword', 'grade_id', 'business_number', 'is_certified']);

        return [
            'shops'   => $this->shopRepository->paginate($perPage, $filters),
            'filters' => $filters,
            'perPage' => $perPage,
            'grades'  => Grade::all(),
        ];
    }

    /**
     * @return array{shop: Shop, grades: \Illuminate\Database\Eloquent\Collection, statuses: array}
     */
    public function getEditData(Shop $shop): array
    {
        $shop->load('admin', 'grade');

        return [
            'shop'     => $shop,
            'grades'   => Grade::all(),
            'statuses' => ShopStatus::cases(),
        ];
    }

    public function updateShop(Shop $shop, array $shopData, array $adminData): void
    {
        $conflict = ShopAdmin::all()
            ->reject(fn ($a) => $a->id === $shop->admin->id)
            ->first(fn ($a) => $a->email === $adminData['email']);

        if ($conflict) {
            throw ValidationException::withMessages(['admin.email' => '此 email 已被使用']);
        }

        $this->shopRepository->update($shop, $shopData);
        $this->shopRepository->updateAdmin($shop->admin, $adminData);
    }

    /**
     * @return array{success: bool, company_name?: string}
     */
    public function verifyCertification(string $businessNumber): array
    {
        try {
            $response = Http::timeout(10)->get(
                'http://data.gcis.nat.gov.tw/od/data/api/5F64D864-61CB-4D0D-8AD9-492047CC1EA6',
                [
                    '$format' => 'json',
                    '$filter' => 'Business_Accounting_NO eq ' . $businessNumber,
                    '$skip'   => 0,
                    '$top'    => 1,
                ]
            );

            $data = $response->json();

            if (! empty($data)) {
                return ['success' => true, 'company_name' => $data[0]['Company_Name']];
            }

            return ['success' => false];
        } catch (\Exception) {
            return ['success' => false];
        }
    }
}
