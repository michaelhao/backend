<?php

namespace App\Services;

use App\Enums\ShopStatus;
use App\Models\Grade;
use App\Models\Shop;
use App\Models\ShopAdmin;
use App\Repositories\GradeRepository;
use App\Repositories\ShopRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ShopService
{
    public function __construct(
        private ShopRepository $shopRepository,
        private GradeRepository $gradeRepository,
        private ShopAddonSyncService $shopAddonSync,
    ) {}

    public function findShopById(int $id): ?Shop
    {
        return $this->shopRepository->getById($id);
    }

    /**
     * @param  array{keyword?: string, grade_id?: string, business_number?: string, is_certified?: string}  $filters
     * @return array{shops: LengthAwarePaginator, filters: array, perPage: int, grades: Collection}
     */
    public function getIndexData(array $filters, int $perPage): array
    {
        return [
            'shops' => $this->shopRepository->paginate($perPage, $filters),
            'filters' => $filters,
            'perPage' => $perPage,
            'grades' => Grade::all(),
        ];
    }

    /**
     * @return array{shop: Shop, grades: Collection, statuses: array}
     */
    public function getEditData(Shop $shop): array
    {
        $shop->load('admin', 'grade');

        return [
            'shop' => $shop,
            'grades' => Grade::all(),
            'statuses' => ShopStatus::cases(),
        ];
    }

    public function updateShop(Shop $shop, array $shopData, array $adminData): void
    {
        $admin = $shop->admin;

        if ($admin) {
            $conflict = ShopAdmin::where('id', '!=', $admin->id)
                ->get()
                ->first(fn ($a) => $a->email === $adminData['email']);

            if ($conflict) {
                throw ValidationException::withMessages(['admin.email' => '此 email 已被使用']);
            }

            $adminData = $this->resolveCertificationData($admin, $adminData);
        }

        $gradeChanging = isset($shopData['grade_id'])
            && (int) $shopData['grade_id'] !== $shop->grade_id;
        $newGradeId = $gradeChanging ? (int) $shopData['grade_id'] : null;
        $shopId = $shop->id;

        DB::transaction(function () use ($shop, $admin, $shopData, $adminData, $gradeChanging, $newGradeId, $shopId) {
            $this->shopRepository->update($shop, $shopData);

            if ($admin) {
                $this->shopRepository->updateAdmin($admin, $adminData);
            }

            if ($gradeChanging) {
                $this->syncShopAddonsOnGradeChange($shopId, $newGradeId);
            }
        });
    }

    /**
     * 認證資料以伺服端為準：business_number 變更時重新呼叫認證 API，
     * company_name 一律採 API 回傳值或 DB 現值，不信任表單送來的值。
     *
     * @param  array{business_number?: ?string, company_name?: ?string}  $adminData
     */
    private function resolveCertificationData(ShopAdmin $admin, array $adminData): array
    {
        if (! array_key_exists('business_number', $adminData)) {
            unset($adminData['company_name']);

            return $adminData;
        }

        $businessNumber = $adminData['business_number'];

        if ($businessNumber === null || $businessNumber === '') {
            $adminData['business_number'] = null;
            $adminData['company_name'] = null;

            return $adminData;
        }

        if ($businessNumber === $admin->business_number) {
            $adminData['company_name'] = $admin->company_name;

            return $adminData;
        }

        $result = $this->verifyCertification($businessNumber);

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'admin.business_number' => '統一編號認證失敗，請重新進行認證',
            ]);
        }

        $adminData['company_name'] = $result['company_name'];

        return $adminData;
    }

    private function syncShopAddonsOnGradeChange(int $shopId, int $newGradeId): void
    {
        $sNew = $this->gradeRepository->getAddonIdsForGrade($newGradeId);

        $this->shopAddonSync->syncForShop($shopId, $sNew);
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
                    '$filter' => 'Business_Accounting_NO eq '.$businessNumber,
                    '$skip' => 0,
                    '$top' => 1,
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
