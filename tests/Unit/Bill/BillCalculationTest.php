<?php

namespace Tests\Unit\Bill;

use App\Services\BillCalculationService;
use Carbon\Carbon;
use Tests\TestCase;

class BillCalculationTest extends TestCase
{
    private BillCalculationService $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new BillCalculationService;
    }

    // ─── calculateExpiredAt ───────────────────────────────────

    public function test_expired_at_when_total_months_zero(): void
    {
        // 2026-04-02 買 0 個月 → 到期 2026-04-30 23:59:59
        $start = Carbon::parse('2026-04-02');
        $result = $this->calc->calculateExpiredAt($start, 0);
        $this->assertEquals('2026-04-30 23:59:59', $result->format('Y-m-d H:i:s'));
    }

    public function test_expired_at_when_start_is_first_of_month(): void
    {
        // 2026-04-01 買 12 個月 → 到期 2027-03-31 23:59:59
        $start = Carbon::parse('2026-04-01');
        $result = $this->calc->calculateExpiredAt($start, 12);
        $this->assertEquals('2027-03-31 23:59:59', $result->format('Y-m-d H:i:s'));
    }

    public function test_expired_at_when_start_is_mid_month(): void
    {
        // 2026-04-17 買 12 個月 → 補 Apr 17-30 + 12 個月 → 到期 2027-04-30 23:59:59
        $start = Carbon::parse('2026-04-17');
        $result = $this->calc->calculateExpiredAt($start, 12);
        $this->assertEquals('2027-04-30 23:59:59', $result->format('Y-m-d H:i:s'));
    }

    // ─── calculatePartialAmount ───────────────────────────────

    public function test_partial_amount_april_17(): void
    {
        // 4 月 17 日: 30 - 17 + 1 = 14 天; 1000 / 30 * 14 = 466.67 → 467
        $start = Carbon::parse('2026-04-17');
        $result = $this->calc->calculatePartialAmount(1000, $start);
        $this->assertEquals(467, $result);
    }

    public function test_partial_amount_february_leap_year(): void
    {
        // 2024-02-15 (閏年 29 天): 29 - 15 + 1 = 15 天; 2900 / 29 * 15 = 1500
        $start = Carbon::parse('2024-02-15');
        $result = $this->calc->calculatePartialAmount(2900, $start);
        $this->assertEquals(1500, $result);
    }

    // ─── calculateDetailTotal ─────────────────────────────────

    public function test_detail_total_first_of_month(): void
    {
        // 2026-04-01 買 12 個月, 1000/月 → 1000 * 12 = 12000（無 partial）
        $start = Carbon::parse('2026-04-01');
        $result = $this->calc->calculateDetailTotal(1000, $start, 12);
        $this->assertEquals(12000, $result);
    }

    public function test_detail_total_zero_months(): void
    {
        // 2026-04-02 買 0 個月 → partial only
        $start = Carbon::parse('2026-04-02');
        $partial = $this->calc->calculatePartialAmount(1000, $start);
        $result = $this->calc->calculateDetailTotal(1000, $start, 0);
        $this->assertEquals($partial, $result);
    }

    public function test_detail_total_mid_month(): void
    {
        // 2026-04-17 買 12 個月 = partial + 12 * 1000
        $start = Carbon::parse('2026-04-17');
        $partial = $this->calc->calculatePartialAmount(1000, $start);
        $result = $this->calc->calculateDetailTotal(1000, $start, 12);
        $this->assertEquals($partial + 12000, $result);
    }

    // ─── calculateUpgradeDiff ─────────────────────────────────

    public function test_upgrade_diff_calculation(): void
    {
        // newPrice=2000, currentPrice=1000, start=2026-04-17, months=12
        // diff=1000; partial=round(1000/30*14)=467; full=2000*12=24000; total=24467
        $start = Carbon::parse('2026-04-17');
        $result = $this->calc->calculateUpgradeDiff(2000, 1000, $start, 12);
        $this->assertEquals(24467, $result);
    }
}
