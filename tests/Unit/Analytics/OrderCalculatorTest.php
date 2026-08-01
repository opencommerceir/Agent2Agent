<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Domain\Services\OrderCalculator;
use PHPUnit\Framework\TestCase;

class OrderCalculatorTest extends TestCase
{
    public function test_calculate_total_passesThroughOrderCount(): void
    {
        $calculator = new OrderCalculator();

        $result = $calculator->calculate(['metric' => 'total', 'totalOrders' => 7]);

        $this->assertSame(7, $result['count']);
    }

    public function test_calculate_averageOrderValue_dividesRevenueByOrders(): void
    {
        $calculator = new OrderCalculator();

        $result = $calculator->calculate([
            'metric' => 'average_order_value',
            'totalRevenueCents' => 10000,
            'totalOrders' => 4,
        ]);

        $this->assertSame(2500, $result['amountCents']);
    }

    public function test_calculate_averageOrderValue_roundsDown(): void
    {
        $calculator = new OrderCalculator();

        $result = $calculator->calculate([
            'metric' => 'average_order_value',
            'totalRevenueCents' => 100,
            'totalOrders' => 3,
        ]);

        $this->assertSame(33, $result['amountCents']);
    }

    public function test_calculate_averageOrderValue_withNoOrders_returnsZeroNotADivisionError(): void
    {
        $calculator = new OrderCalculator();

        $result = $calculator->calculate([
            'metric' => 'average_order_value',
            'totalRevenueCents' => 0,
            'totalOrders' => 0,
        ]);

        $this->assertSame(0, $result['amountCents']);
    }
}
