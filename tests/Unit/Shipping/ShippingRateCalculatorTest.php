<?php

namespace Tests\Unit\Shipping;

use App\Modules\Shipping\Domain\Services\ShippingRateCalculator;
use App\Modules\Shipping\Domain\ValueObjects\Money;
use App\Modules\Shipping\Domain\ValueObjects\Weight;
use PHPUnit\Framework\TestCase;

class ShippingRateCalculatorTest extends TestCase
{
    private ShippingRateCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ShippingRateCalculator();
    }

    public function test_calculate_appliesBaseRatePlusWeightBasedRate(): void
    {
        $rate = $this->calculator->calculate(
            Money::fromAmount(500, 'USD'),
            Money::fromAmount(100, 'USD'),
            new Weight(2500),
            2,
            5,
        );

        // 500 + (2.5kg * 100) = 750.
        $this->assertSame(750, $rate->cost()->amount());
        $this->assertSame('USD', $rate->cost()->currency());
        $this->assertSame(2, $rate->estimatedDaysMin());
        $this->assertSame(5, $rate->estimatedDaysMax());
    }

    public function test_calculate_withZeroWeight_isJustTheBaseRate(): void
    {
        $rate = $this->calculator->calculate(
            Money::fromAmount(500, 'USD'),
            Money::fromAmount(100, 'USD'),
            new Weight(0),
            2,
            5,
        );

        $this->assertSame(500, $rate->cost()->amount());
    }

    public function test_calculate_roundsToNearestCent(): void
    {
        $rate = $this->calculator->calculate(
            Money::fromAmount(0, 'USD'),
            Money::fromAmount(100, 'USD'),
            new Weight(333), // 0.333kg * 100 = 33.3 -> rounds to 33
            1,
            1,
        );

        $this->assertSame(33, $rate->cost()->amount());
    }
}
