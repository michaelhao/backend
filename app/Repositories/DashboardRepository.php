<?php

namespace App\Repositories;

use App\Models\Conference;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class DashboardRepository
{
    private function todayRange(): array
    {
        return [
            Carbon::now()->startOfDay(),
            Carbon::now()->endOfDay(),
        ];
    }

    public function getMyNewShopsToday(int $userId): Collection
    {
        [$start, $end] = $this->todayRange();

        return Shop::query()
            ->with('admin')
            ->where('sales_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get();
    }

    public function getTodayConferences(): Collection
    {
        [$start, $end] = $this->todayRange();

        return Conference::query()
            ->whereBetween('started_at', [$start, $end])
            ->orderBy('started_at')
            ->get();
    }

    public function getMyExpiringShops(int $userId): Collection
    {
        $now = Carbon::now();

        return Shop::query()
            ->where('sales_id', $userId)
            ->whereBetween('expired_at', [$now, $now->copy()->addMonths(6)->endOfDay()])
            ->orderBy('expired_at')
            ->get();
    }
}
