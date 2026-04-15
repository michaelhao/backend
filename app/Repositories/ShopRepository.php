<?php

namespace App\Repositories;

use App\Models\Shop;
use App\Models\ShopAdmin;
use Illuminate\Pagination\LengthAwarePaginator;

class ShopRepository
{
    /**
     * @param array{keyword?: string, grade_id?: string, business_number?: string, is_certified?: string} $filters
     */
    public function paginate(int $perPage, array $filters): LengthAwarePaginator
    {
        return Shop::query()
            ->with(['admin', 'grade'])
            ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                $query->where('name', 'like', "%{$keyword}%");
            })
            ->when($filters['grade_id'] ?? null, function ($query, $gradeId) {
                $query->where('grade_id', $gradeId);
            })
            ->when($filters['business_number'] ?? null, function ($query, $businessNumber) {
                $query->whereHas('admin', fn ($q) => $q->where('business_number', $businessNumber));
            })
            ->when(
                ($filters['is_certified'] ?? '') !== '',
                function ($query) use ($filters) {
                    if ($filters['is_certified'] === '1') {
                        $query->whereHas('admin', fn ($q) => $q->whereNotNull('business_number'));
                    } else {
                        $query->whereHas('admin', fn ($q) => $q->whereNull('business_number'));
                    }
                }
            )
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function update(Shop $shop, array $data): void
    {
        $shop->update($data);
    }

    public function updateAdmin(ShopAdmin $admin, array $data): void
    {
        $admin->update($data);
    }
}
