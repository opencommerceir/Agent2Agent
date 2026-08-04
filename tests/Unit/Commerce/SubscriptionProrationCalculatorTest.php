<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Services\SubscriptionProrationCalculator;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SubscriptionProrationCalculatorTest extends TestCase
{
    private SubscriptionProrationCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new SubscriptionProrationCalculator();
    }

    public function test_calculate_atExactMidpoint_chargesHalfTheDifference(): void
    {
        $start = new DateTimeImmutable('2026-01-01');
        $end = new DateTimeImmutable('2026-01-31'); // 30-day period
        $now = new DateTimeImmutable('2026-01-16'); // 15 days remaining

        $charge = $this->calculator->calculate(
            oldPrice: Money::fromAmount(3000, 'USD'),
            newPrice: Money::fromAmount(6000, 'USD'),
            periodStart: $start,
            periodEnd: $end,
            now: $now,
        );

        // credit = 3000 * 15/30 = 1500; newCost = 6000 * 15/30 = 3000; charge = 1500
        $this->assertSame(1500, $charge);
    }

    public function test_calculate_downgradeWithExcessCredit_neverGoesNegative(): void
    {
        $start = new DateTimeImmutable('2026-01-01');
        $end = new DateTimeImmutable('2026-01-31');
        $now = new DateTimeImmutable('2026-01-16');

        $charge = $this->calculator->calculate(
            oldPrice: Money::fromAmount(6000, 'USD'),
            newPrice: Money::fromAmount(3000, 'USD'),
            periodStart: $start,
            periodEnd: $end,
            now: $now,
        );

        $this->assertSame(0, $charge);
    }

    public function test_calculate_atPeriodEnd_hasNoRemainingDaysSoChargesNothing(): void
    {
        $start = new DateTimeImmutable('2026-01-01');
        $end = new DateTimeImmutable('2026-01-31');

        $charge = $this->calculator->calculate(
            oldPrice: Money::fromAmount(3000, 'USD'),
            newPrice: Money::fromAmount(6000, 'USD'),
            periodStart: $start,
            periodEnd: $end,
            now: $end,
        );

        $this->assertSame(0, $charge);
    }

    public function test_calculate_atPeriodStart_chargesFullDifference(): void
    {
        $start = new DateTimeImmutable('2026-01-01');
        $end = new DateTimeImmutable('2026-01-31');

        $charge = $this->calculator->calculate(
            oldPrice: Money::fromAmount(3000, 'USD'),
            newPrice: Money::fromAmount(6000, 'USD'),
            periodStart: $start,
            periodEnd: $end,
            now: $start,
        );

        $this->assertSame(3000, $charge);
    }
}
