<?php

namespace App\Repositories;

use App\Models\Conference;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class DashboardRepository
{
    // TODO: change-app-timezone-to-taipei 落地後可改用 today() / now()，移除 'Asia/Taipei' 參數
    private function todayRange(): array
    {
        return [
            Carbon::now('Asia/Taipei')->startOfDay(),
            Carbon::now('Asia/Taipei')->endOfDay(),
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
        $now = Carbon::now('Asia/Taipei');

        return Shop::query()
            ->where('sales_id', $userId)
            ->whereBetween('expired_at', [$now, $now->copy()->addMonths(6)->endOfDay()])
            ->orderBy('expired_at')
            ->get();
    }
}
