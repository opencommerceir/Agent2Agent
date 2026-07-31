<?php

namespace Tests\Unit\Loyalty;

use App\Modules\Loyalty\Domain\Services\PointsCalculationService;
use PHPUnit\Framework\TestCase;

class PointsCalculationServiceTest extends TestCase
{
    private PointsCalculationService $service;

    protected function setUp(): void
    {
        $this->service = new PointsCalculationService();
    }

    public function test_calculateForAmount_wholeDollars_earnsOnePointPerDollar(): void
    {
        $this->assertSame(150, $this->service->calculateForAmount(15000));
    }

    public function test_calculateForAmount_roundsDownFractionalDollars(): void
    {
        $this->assertSame(1, $this->service->calculateForAmount(150));
    }

    public function test_calculateForAmount_belowOneDollar_earnsZeroPoints(): void
    {
        $this->assertSame(0, $this->service->calculateForAmount(99));
    }

    public function test_calculateForAmount_withZero_earnsZeroPoints(): void
    {
        $this->assertSame(0, $this->service->calculateForAmount(0));
    }
}
