<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\DashboardRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    public function __construct(
        private DashboardRepository $repo,
    ) {}

    /**
     * @return array{
     *     new_shops: list<array{id: int, name: string, created_at_hm: string, contact: string|null}>,
     *     today_conferences: list<array{id: int, name: string, time_range: string}>,
     *     expiring_shops: list<array{id: int, name: string, expired_at: string, days: int, color: string}>,
     *     can_edit_shop: bool,
     *     can_edit_conference: bool,
     * }
     */
    public function getOverview(int $userId, User $user): array
    {
        return [
            'new_shops' => $this->mapNewShops($this->repo->getMyNewShopsToday($userId)),
            'today_conferences' => $this->mapConferences($this->repo->getTodayConferences()),
            'expiring_shops' => $this->mapExpiringShops($this->repo->getMyExpiringShops($userId)),
            'can_edit_shop' => $user->hasPermissionTo('Shop.update'),
            'can_edit_conference' => $user->hasPermissionTo('Conference.update'),
        ];
    }

    /**
     * @return list<array{id: int, name: string, created_at_hm: string, contact: string|null}>
     */
    private function mapNewShops(Collection $shops): array
    {
        return $shops->map(fn ($shop) => [
            'id' => $shop->id,
            'name' => $shop->name,
            'created_at_hm' => $shop->created_at->setTimezone('Asia/Taipei')->format('H:i'),
            'contact' => optional($shop->admin)->name,
        ])->all();
    }

    /**
     * @return list<array{id: int, name: string, time_range: string}>
     */
    private function mapConferences(Collection $conferences): array
    {
        return $conferences->map(fn ($conference) => [
            'id' => $conference->id,
            'name' => $conference->name,
            'time_range' => $conference->started_at->setTimezone('Asia/Taipei')->format('H:i')
                .'–'
                .$conference->ended_at->setTimezone('Asia/Taipei')->format('H:i'),
        ])->all();
    }

    /**
     * @return list<array{id: int, name: string, expired_at: string, days: int, color: string}>
     */
    private function mapExpiringShops(Collection $shops): array
    {
        return $shops->map(fn ($shop) => [
            'id' => $shop->id,
            'name' => $shop->name,
            'expired_at' => $shop->expired_at->setTimezone('Asia/Taipei')->format('Y-m-d'),
            'days' => $this->daysToExpire($shop->expired_at),
            'color' => $this->daysColor($this->daysToExpire($shop->expired_at)),
        ])->all();
    }

    private function daysToExpire(Carbon $expiredAt): int
    {
        return (int) Carbon::today()
            ->diffInDays(Carbon::parse($expiredAt->toDateString()));
    }

    private function daysColor(int $d): string
    {
        return $d <= 60 ? '#ef4444' : ($d <= 90 ? '#f97316' : '#ca8a04');
    }
}
