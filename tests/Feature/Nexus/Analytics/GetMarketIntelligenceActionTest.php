<?php

namespace Tests\Feature\Nexus\Analytics;

use App\Domains\Nexus\Analytics\Application\Actions\GetMarketIntelligenceAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Credit\Application\Actions\GetCreditBalanceAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GetMarketIntelligenceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_unknownBusiness_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(GetMarketIntelligenceAction::class)->execute(999999);
    }

    public function test_execute_defaultsToOwnIndustry(): void
    {
        $business = $this->verifiedBusiness('Caller Co', 'healthcare');

        $result = app(GetMarketIntelligenceAction::class)->execute($business->id);

        $this->assertSame('healthcare', $result['industry']);
    }

    public function test_execute_chargesCostGate(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $before = app(GetCreditBalanceAction::class)->execute($business->id)->balance;

        app(GetMarketIntelligenceAction::class)->execute($business->id);

        $after = app(GetCreditBalanceAction::class)->execute($business->id)->balance;
        $this->assertSame(5, $before - $after);
    }

    public function test_execute_priceTrendAndDemandSignal_countAcceptedDealsAgainstIndustrySellers(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co', 'retail');
        $seller = $this->verifiedBusiness('Seller Co', 'technology');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $result = app(GetMarketIntelligenceAction::class)->execute($buyer->id, 'technology');

        $this->assertCount(1, $result['demandSignal']);
        $this->assertSame(1, $result['demandSignal'][0]['proposalsCount']);
        $this->assertCount(1, $result['priceTrend']);
        $this->assertSame(10_000, $result['priceTrend'][0]['averageUnitAmount']);
    }

    public function test_execute_competitorStats_suppressedBelowMinSampleSize(): void
    {
        config(['nexus.platform.analytics.min_market_intelligence_sample_size' => 3]);
        $caller = $this->verifiedBusiness('Caller Co', 'technology');
        $onlyCompetitor = $this->verifiedBusiness('Only Competitor', 'technology');
        app(AddProductAction::class)->execute($onlyCompetitor->id, 'محصول', 'Widget', 10_000, 'IRT', 10);

        $result = app(GetMarketIntelligenceAction::class)->execute($caller->id, 'technology');

        $this->assertNull($result['competitorStats']['averageProductPriceAmount']);
        $this->assertSame(1, $result['competitorStats']['competitorCount']);
    }

    public function test_execute_competitorStats_computesOnceEnoughCompetitorsExist(): void
    {
        config(['nexus.platform.analytics.min_market_intelligence_sample_size' => 2]);
        $caller = $this->verifiedBusiness('Caller Co', 'technology');
        $competitorA = $this->verifiedBusiness('Competitor A', 'technology');
        app(AddProductAction::class)->execute($competitorA->id, 'محصول', 'Widget', 8_000, 'IRT', 10);
        $competitorB = $this->verifiedBusiness('Competitor B', 'technology');
        app(AddProductAction::class)->execute($competitorB->id, 'محصول', 'Widget', 12_000, 'IRT', 10);

        $result = app(GetMarketIntelligenceAction::class)->execute($caller->id, 'technology');

        $this->assertSame(10_000, $result['competitorStats']['averageProductPriceAmount']);
        $this->assertSame(2, $result['competitorStats']['competitorCount']);
    }

    private function verifiedBusiness(string $nameEn, string $industry = 'technology'): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::from($industry));
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}
