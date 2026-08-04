<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Services\SubscriptionBillingCalculator;
use App\Modules\Commerce\Domain\ValueObjects\BillingCycle;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SubscriptionBillingCalculatorTest extends TestCase
{
    private SubscriptionBillingCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new SubscriptionBillingCalculator();
    }

    public function test_nextPeriodEnd_monthly_addsOneMonth(): void
    {
        $result = $this->calculator->nextPeriodEnd(new DateTimeImmutable('2026-01-15'), BillingCycle::Monthly);

        $this->assertSame('2026-02-15', $result->format('Y-m-d'));
    }

    public function test_nextPeriodEnd_quarterly_addsThreeMonths(): void
    {
        $result = $this->calculator->nextPeriodEnd(new DateTimeImmutable('2026-01-15'), BillingCycle::Quarterly);

        $this->assertSame('2026-04-15', $result->format('Y-m-d'));
    }

    public function test_nextPeriodEnd_yearly_addsOneYear(): void
    {
        $result = $this->calculator->nextPeriodEnd(new DateTimeImmutable('2026-01-15'), BillingCycle::Yearly);

        $this->assertSame('2027-01-15', $result->format('Y-m-d'));
    }

    public function test_nextPeriodEnd_monthly_fromMonthEnd_rollsCalendarCorrectly(): void
    {
        $result = $this->calculator->nextPeriodEnd(new DateTimeImmutable('2026-01-31'), BillingCycle::Monthly);

        // PHP's own DateTimeImmutable::modify('+1 month') calendar-rolls
        // Jan 31 -> Mar 3 (Feb has no 31st) rather than clamping to Feb 28 —
        // documented here so a future reader isn't surprised by it.
        $this->assertSame('2026-03-03', $result->format('Y-m-d'));
    }
}
