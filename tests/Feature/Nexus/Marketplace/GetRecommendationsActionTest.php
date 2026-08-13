<?php

namespace Tests\Feature\Nexus\Marketplace;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Marketplace\Application\Actions\GetRecommendationsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GetRecommendationsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returnsOnlySameIndustryVerifiedBusinesses(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);
        // Phase 8/M3's CostGate now gates nexus.marketplace.recommendations
        // — a generous flat top-up so this domain's own test keeps
        // exercising recommendation mechanics, not credit exhaustion (same
        // reasoning SearchMarketplaceActionTest already applies).
        app(GrantCreditsAction::class)->execute($caller->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        $sameIndustry = app(RegisterBusinessAction::class)->execute('همان صنعت', 'Same Industry Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($sameIndustry->id);

        $otherIndustry = app(RegisterBusinessAction::class)->execute('صنعت دیگر', 'Other Industry Co', BusinessType::Company, Industry::Retail);
        app(VerifyBusinessAction::class)->execute($otherIndustry->id);

        $result = app(GetRecommendationsAction::class)->execute($caller->id);

        $businessIds = array_column($result['listings'], 'businessId');
        $this->assertContains($sameIndustry->id, $businessIds);
        $this->assertNotContains($otherIndustry->id, $businessIds);
        $this->assertNotContains($caller->id, $businessIds);
    }

    public function test_execute_withNonExistentBusiness_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(GetRecommendationsAction::class)->execute(9999);
    }
}
