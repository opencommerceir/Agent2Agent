<?php

namespace Tests\Feature\Nexus\Analytics;

use App\Domains\Nexus\Analytics\Application\Actions\GetBusinessAnalyticsAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectDealAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GetBusinessAnalyticsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_unknownBusiness_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(GetBusinessAnalyticsAction::class)->execute(999999);
    }

    public function test_execute_noDeals_returnsHonestZeroDefaults(): void
    {
        $business = $this->verifiedBusiness('Fresh Co');

        $result = app(GetBusinessAnalyticsAction::class)->execute($business->id);

        $this->assertSame(0.0, $result['successRate']);
        $this->assertSame(0, $result['completedDeals']);
        $this->assertSame(['accepted' => 0, 'rejected' => 0, 'expired' => 0, 'open' => 0], $result['dealCounts']);
        $this->assertSame(0, $result['savings']['dealCount']);
    }

    public function test_execute_computesDealCountsAndSuccessRate(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $sellerA = $this->verifiedBusiness('Seller A');
        $sellerB = $this->verifiedBusiness('Seller B');

        $accepted = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $sellerA->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($accepted->id, $buyer->id);

        $rejected = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $sellerB->id, CatalogItemType::Product, 2,
            new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null),
        );
        app(RejectDealAction::class)->execute($rejected->id, $sellerB->id, null);

        $result = app(GetBusinessAnalyticsAction::class)->execute($buyer->id);

        $this->assertSame(0.5, $result['successRate']);
        $this->assertSame(1, $result['dealCounts']['accepted']);
        $this->assertSame(1, $result['dealCounts']['rejected']);
    }

    public function test_execute_computesSavingsAgainstListedCatalogPrice(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $product = app(AddProductAction::class)->execute($seller->id, 'محصول', 'Widget', 12_000, 'IRT', 100);

        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, $product->id,
            new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 2, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $result = app(GetBusinessAnalyticsAction::class)->execute($buyer->id);

        // (12,000 listed - 10,000 negotiated) * 2 units = 4,000 saved.
        $this->assertSame(1, $result['savings']['dealCount']);
        $this->assertSame(4_000, $result['savings']['totalsByCurrency']['IRT']);
    }

    public function test_execute_priceBenchmark_suppressedBelowMinSampleSize(): void
    {
        config(['nexus.platform.analytics.min_benchmark_sample_size' => 3]);
        $business = $this->verifiedBusiness('Solo Co');
        app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 10);
        $onlyCompetitor = $this->verifiedBusiness('Only Competitor Co');
        app(AddProductAction::class)->execute($onlyCompetitor->id, 'محصول', 'Widget', 8_000, 'IRT', 10);

        $result = app(GetBusinessAnalyticsAction::class)->execute($business->id);

        $this->assertSame(10_000, $result['priceBenchmark']['product']['ownAverageAmount']);
        $this->assertNull($result['priceBenchmark']['product']['industryAverageAmount']);
        $this->assertSame(1, $result['priceBenchmark']['product']['industrySampleBusinessCount']);
    }

    public function test_execute_priceBenchmark_computesOnceEnoughCompetitorsExist(): void
    {
        config(['nexus.platform.analytics.min_benchmark_sample_size' => 2]);
        $business = $this->verifiedBusiness('Main Co');
        app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 10);
        $competitorA = $this->verifiedBusiness('Competitor A');
        app(AddProductAction::class)->execute($competitorA->id, 'محصول', 'Widget', 8_000, 'IRT', 10);
        $competitorB = $this->verifiedBusiness('Competitor B');
        app(AddProductAction::class)->execute($competitorB->id, 'محصول', 'Widget', 12_000, 'IRT', 10);

        $result = app(GetBusinessAnalyticsAction::class)->execute($business->id);

        $this->assertSame(10_000, $result['priceBenchmark']['product']['industryAverageAmount']);
        $this->assertSame(2, $result['priceBenchmark']['product']['industrySampleBusinessCount']);
    }

    private function verifiedBusiness(string $nameEn, string $industry = 'technology'): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::from($industry));
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}
