<?php

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Domain\Services\LoyaltyReportGenerator;
use PHPUnit\Framework\TestCase;

class LoyaltyReportGeneratorTest extends TestCase
{
    public function test_generate_assemblesTotalsAndTopEarners(): void
    {
        $generator = new LoyaltyReportGenerator();

        $result = $generator->generate(
            totalPointsEarned: 500,
            totalPointsRedeemed: 100,
            activeAccounts: 3,
            topEarnerRows: [['loyalty_account_id' => 1, 'customer_id' => 10, 'points_earned' => 300]],
            customerNames: [10 => 'Jane Doe'],
        );

        $this->assertSame(500, $result['totalPointsEarned']);
        $this->assertSame(100, $result['totalPointsRedeemed']);
        $this->assertSame(3, $result['activeAccounts']);
        $this->assertSame([
            ['customer_id' => 10, 'name' => 'Jane Doe', 'points_earned' => 300],
        ], $result['topEarners']);
    }
}
