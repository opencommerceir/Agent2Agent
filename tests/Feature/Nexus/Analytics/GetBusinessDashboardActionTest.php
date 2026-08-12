<?php

namespace Tests\Feature\Nexus\Analytics;

use App\Domains\Nexus\Analytics\Application\Actions\GetBusinessDashboardAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GetBusinessDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_forUnverifiedBusinessWithEmptyCatalog_returnsNullAgentAndZeroCounts(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $result = app(GetBusinessDashboardAction::class)->execute($business->id);

        $this->assertSame($business->id, $result['business']->id());
        $this->assertNull($result['agent']);
        $this->assertSame(0, $result['productCount']);
        $this->assertSame(0, $result['serviceCount']);
        $this->assertNull($result['creditBalance']);
        $this->assertNull($result['activeNegotiations']);
    }

    public function test_execute_forVerifiedBusinessWithProducts_returnsAgentAndCounts(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(AddProductAction::class)->execute($business->id, 'محصول', 'Product A', 1000, 'IRT');

        $result = app(GetBusinessDashboardAction::class)->execute($business->id);

        $this->assertNotNull($result['agent']);
        $this->assertSame(1, $result['productCount']);
        // Verification also opens a CreditBalance row (0 by default in
        // this env — GrantStartingCreditsOnBusinessVerifiedListener) —
        // no longer the honest-null placeholder for a verified Business.
        $this->assertSame(0, $result['creditBalance']);
    }

    public function test_execute_withNonExistentBusiness_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(GetBusinessDashboardAction::class)->execute(9999);
    }
}
