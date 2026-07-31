<?php

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Domain\Services\RevenueReportGenerator;
use PHPUnit\Framework\TestCase;

class RevenueReportGeneratorTest extends TestCase
{
    public function test_generate_netRevenueSubtractsOnlyDiscounts_notTax(): void
    {
        $generator = new RevenueReportGenerator();

        $result = $generator->generate(100000, 9000, 5000);

        $this->assertSame(100000, $result['grossRevenue']);
        $this->assertSame(9000, $result['taxCollected']);
        $this->assertSame(5000, $result['discountsApplied']);
        $this->assertSame(95000, $result['netRevenue']);
    }
}
