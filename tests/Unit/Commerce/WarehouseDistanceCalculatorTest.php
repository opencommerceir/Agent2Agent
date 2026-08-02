<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Services\WarehouseDistanceCalculator;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;
use PHPUnit\Framework\TestCase;

class WarehouseDistanceCalculatorTest extends TestCase
{
    private WarehouseDistanceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new WarehouseDistanceCalculator();
    }

    public function test_calculate_tehranToIsfahan_isApproximatelyKnownDistance(): void
    {
        $tehran = new WarehouseLocation(35.6892, 51.3890, 'Tehran, Iran');
        $isfahan = new WarehouseLocation(32.6546, 51.6680, 'Isfahan, Iran');

        $distance = $this->calculator->calculate($tehran, $isfahan);

        // Real-world great-circle distance is roughly 340-410 km.
        $this->assertEqualsWithDelta(340.0, $distance, 20.0);
    }

    public function test_calculate_samePoint_isApproximatelyZero(): void
    {
        $point = new WarehouseLocation(35.6892, 51.3890, 'Tehran, Iran');

        $distance = $this->calculator->calculate($point, $point);

        $this->assertEqualsWithDelta(0.0, $distance, 0.001);
    }
}
