<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Domain\Services\ConversionRateCalculator;
use PHPUnit\Framework\TestCase;

class ConversionRateCalculatorTest extends TestCase
{
    public function test_calculate_computesPercentOfCartsThatBecameOrders(): void
    {
        $calculator = new ConversionRateCalculator();

        $result = $calculator->calculate(['totalCarts' => 20, 'totalOrders' => 5]);

        $this->assertSame(25.0, $result['conversionRatePercent']);
    }

    public function test_calculate_withNoCarts_returnsZeroNotADivisionError(): void
    {
        $calculator = new ConversionRateCalculator();

        $result = $calculator->calculate(['totalCarts' => 0, 'totalOrders' => 0]);

        $this->assertSame(0.0, $result['conversionRatePercent']);
    }
}
