<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Domain\Services\RevenueCalculator;
use PHPUnit\Framework\TestCase;

class RevenueCalculatorTest extends TestCase
{
    public function test_calculate_total_passesThroughGrossRevenue(): void
    {
        $calculator = new RevenueCalculator();

        $result = $calculator->calculate(['metric' => 'total', 'grossRevenueCents' => 150000]);

        $this->assertSame(150000, $result['amountCents']);
    }

    public function test_calculate_growthRate_computesPercentChange(): void
    {
        $calculator = new RevenueCalculator();

        $result = $calculator->calculate([
            'metric' => 'growth_rate',
            'currentPeriodCents' => 15000,
            'previousPeriodCents' => 10000,
        ]);

        $this->assertSame(50.0, $result['growthRatePercent']);
    }

    public function test_calculate_growthRate_withNegativeChange(): void
    {
        $calculator = new RevenueCalculator();

        $result = $calculator->calculate([
            'metric' => 'growth_rate',
            'currentPeriodCents' => 5000,
            'previousPeriodCents' => 10000,
        ]);

        $this->assertSame(-50.0, $result['growthRatePercent']);
    }

    public function test_calculate_growthRate_withNoPreviousRevenue_returnsNullNotADivisionError(): void
    {
        $calculator = new RevenueCalculator();

        $result = $calculator->calculate([
            'metric' => 'growth_rate',
            'currentPeriodCents' => 5000,
            'previousPeriodCents' => 0,
        ]);

        $this->assertNull($result['growthRatePercent']);
    }
}
