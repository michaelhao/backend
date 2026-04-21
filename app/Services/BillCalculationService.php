<?php

namespace App\Services;

use Carbon\Carbon;

class BillCalculationService
{
    /**
     * Calculate expired_at given start_at and total_months.
     *
     * total_months=0: expire at end of start month (23:59:59)
     * start_at is 1st of month: expire at end of (total_months - 1) months later
     * otherwise: expire at end of (total_months) full months after start month
     */
    public function calculateExpiredAt(Carbon $startAt, int $totalMonths): Carbon
    {
        if ($totalMonths === 0) {
            return $startAt->copy()->endOfMonth()->setTime(23, 59, 59);
        }

        if ($startAt->day === 1) {
            // Month 1 is the start month itself
            return $startAt->copy()->addMonths($totalMonths)->subDay()->setTime(23, 59, 59);
        }

        // Partial month + totalMonths full months
        return $startAt->copy()->addMonths($totalMonths)->endOfMonth()->setTime(23, 59, 59);
    }

    /**
     * Calculate total_price for a detail line.
     */
    public function calculateDetailTotal(int $unitPrice, Carbon $startAt, int $totalMonths): int
    {
        if ($totalMonths === 0) {
            return $this->calculatePartialAmount($unitPrice, $startAt);
        }

        if ($startAt->day === 1) {
            return $unitPrice * $totalMonths;
        }

        $partial = $this->calculatePartialAmount($unitPrice, $startAt);

        return $partial + ($unitPrice * $totalMonths);
    }

    /**
     * Partial month amount: from start_at to end of that month (inclusive).
     * Uses Carbon::daysInMonth — no hardcoded month lengths.
     */
    public function calculatePartialAmount(int $unitPrice, Carbon $startAt): int
    {
        $daysInMonth = $startAt->daysInMonth;
        $remainingDays = $daysInMonth - $startAt->day + 1;

        return (int) round($unitPrice / $daysInMonth * $remainingDays);
    }

    /**
     * Upgrade fee diff (type=2): used when start_at equals shop's current expired_at date.
     *
     * diff_price = new_grade_price - current_grade_price (list price)
     * partial = round( diff_price / daysInMonth × remaining_days )
     * full    = new_grade_price × total_months
     */
    public function calculateUpgradeDiff(int $newPrice, int $currentPrice, Carbon $startAt, int $totalMonths): int
    {
        $diffPrice = $newPrice - $currentPrice;
        $daysInMonth = $startAt->daysInMonth;
        $remainingDays = $daysInMonth - $startAt->day + 1;

        $partial = (int) round($diffPrice / $daysInMonth * $remainingDays);
        $full = $newPrice * $totalMonths;

        return $partial + $full;
    }
}
