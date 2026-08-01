<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Domain\Services\CustomerCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CustomerCalculatorTest extends TestCase
{
    public function test_calculate_total_countsAllCustomers(): void
    {
        $calculator = new CustomerCalculator();

        $result = $calculator->calculate([
            'metric' => 'total',
            'customerCreatedAt' => [new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-06-01')],
        ]);

        $this->assertSame(2, $result['count']);
    }

    public function test_calculate_new_countsOnlyCustomersCreatedWithinThePeriod(): void
    {
        $calculator = new CustomerCalculator();

        $result = $calculator->calculate([
            'metric' => 'new',
            'customerCreatedAt' => [
                new DateTimeImmutable('2026-01-01'),
                new DateTimeImmutable('2026-07-15'),
                new DateTimeImmutable('2026-07-20'),
            ],
            'periodStart' => new DateTimeImmutable('2026-07-01'),
            'periodEnd' => new DateTimeImmutable('2026-07-31'),
        ]);

        $this->assertSame(2, $result['count']);
    }

    public function test_calculate_retentionRate_computesPercentOfRepeatCustomers(): void
    {
        $calculator = new CustomerCalculator();

        $result = $calculator->calculate(['metric' => 'retention_rate', 'repeatCustomers' => 3, 'totalCustomers' => 12]);

        $this->assertSame(25.0, $result['retentionRatePercent']);
    }

    public function test_calculate_retentionRate_withNoCustomers_returnsZero(): void
    {
        $calculator = new CustomerCalculator();

        $result = $calculator->calculate(['metric' => 'retention_rate', 'repeatCustomers' => 0, 'totalCustomers' => 0]);

        $this->assertSame(0.0, $result['retentionRatePercent']);
    }

    public function test_calculate_lifetimeValue_dividesRevenueByCustomers(): void
    {
        $calculator = new CustomerCalculator();

        $result = $calculator->calculate(['metric' => 'lifetime_value', 'totalRevenueCents' => 10000, 'totalCustomers' => 4]);

        $this->assertSame(2500, $result['amountCents']);
    }
}
