<?php

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Domain\Services\SalesReportGenerator;
use PHPUnit\Framework\TestCase;

class SalesReportGeneratorTest extends TestCase
{
    private SalesReportGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SalesReportGenerator();
    }

    public function test_generate_computesAverageOrderValue(): void
    {
        $result = $this->generator->generate(15000, 3, ['2026-07-01' => 15000]);

        $this->assertSame(15000, $result['totalSales']);
        $this->assertSame(3, $result['totalOrders']);
        $this->assertSame(5000, $result['averageOrderValue']);
        $this->assertSame(['2026-07-01' => 15000], $result['salesByDay']);
    }

    public function test_generate_withZeroOrders_averageIsZeroNotDivisionError(): void
    {
        $result = $this->generator->generate(0, 0, []);

        $this->assertSame(0, $result['averageOrderValue']);
    }

    public function test_generate_averageRoundsDown(): void
    {
        $result = $this->generator->generate(100, 3, []);

        $this->assertSame(33, $result['averageOrderValue']);
    }
}
