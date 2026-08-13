<?php

namespace Tests\Feature\Nexus\Analytics;

use App\Domains\Nexus\Analytics\Application\Actions\AssessDealRiskAction;
use App\Domains\Nexus\Analytics\Application\Actions\ForecastSupplierReliabilityAction;
use App\Domains\Nexus\Analytics\Application\Actions\SimulateNegotiationScenarioAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Application\Actions\ArbitrateDisputeAction;
use App\Domains\Nexus\Contract\Application\Actions\DisputeEscrowAction;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\GetCreditBalanceAction;
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

class PredictiveIntelligenceActionsTest extends TestCase
{
    use RefreshDatabase;

    // --- ForecastSupplierReliabilityAction ---

    public function test_forecast_unknownBusiness_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ForecastSupplierReliabilityAction::class)->execute(999999);
    }

    public function test_forecast_noHistory_isInsufficientData(): void
    {
        $business = $this->verifiedBusiness('Fresh Co');

        $result = app(ForecastSupplierReliabilityAction::class)->execute($business->id);

        $this->assertSame('insufficient_data', $result['trend']);
    }

    public function test_forecast_reportsRecentRate_evenWhenOverallTrendIsInsufficientData(): void
    {
        config(['nexus.platform.intelligence.trend_min_sample_size' => 1]);
        $business = $this->verifiedBusiness('Supplier Co');
        $buyer = $this->verifiedBusiness('Buyer Co');

        $accepted = app(InitiateNegotiationAction::class)->execute($buyer->id, $business->id, CatalogItemType::Product, 1, new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null));
        app(AcceptDealAction::class)->execute($accepted->id, $buyer->id);

        $result = app(ForecastSupplierReliabilityAction::class)->execute($business->id);

        // Only the recent window has any data — the prior window has zero
        // outcomes, so the trend itself is honestly 'insufficient_data'
        // (comparing to nothing isn't a real trend), even though the
        // recent rate itself is a real, reportable number.
        $this->assertSame('insufficient_data', $result['trend']);
        $this->assertSame(1.0, $result['recentSuccessRate']);
        $this->assertNull($result['priorSuccessRate']);
    }

    // --- AssessDealRiskAction ---

    public function test_risk_unknownCounterparty_throws(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');

        $this->expectException(InvalidArgumentException::class);

        app(AssessDealRiskAction::class)->execute($caller->id, 999999, 10_000, 'IRT');
    }

    public function test_risk_chargesCostGate(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $counterparty = $this->verifiedBusiness('Counterparty Co');
        $before = app(GetCreditBalanceAction::class)->execute($caller->id)->balance;

        app(AssessDealRiskAction::class)->execute($caller->id, $counterparty->id, 10_000, 'IRT');

        $after = app(GetCreditBalanceAction::class)->execute($caller->id)->balance;
        $this->assertSame(5, $before - $after);
    }

    public function test_risk_freshCounterpartyWithZeroReputation_scoresHighOnReputationAlone(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $counterparty = $this->verifiedBusiness('Fresh Counterparty Co');

        $result = app(AssessDealRiskAction::class)->execute($caller->id, $counterparty->id, 10_000, 'IRT');

        // score = 0 (no deals, no reviews) -> reputationPoints = 50 (max)
        $this->assertSame(50, $result['factors']['reputationPoints']);
        $this->assertSame(0, $result['disputesLostRecent']);
        $this->assertNull($result['dealSizeRatio']);
    }

    public function test_risk_recentLostDispute_addsDisputePoints(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null));
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(DisputeEscrowAction::class)->execute($negotiation->id, $buyer->id, 'never delivered');
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiation->id);
        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());
        app(ArbitrateDisputeAction::class)->execute($disputeCase->id(), 'refund_buyer'); // seller loses

        $result = app(AssessDealRiskAction::class)->execute($buyer->id, $seller->id, 10_000, 'IRT');

        $this->assertSame(1, $result['disputesLostRecent']);
        $this->assertSame(10, $result['factors']['disputePoints']);
    }

    // --- SimulateNegotiationScenarioAction ---

    public function test_scenario_unknownCounterparty_throws(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');

        $this->expectException(InvalidArgumentException::class);

        app(SimulateNegotiationScenarioAction::class)->execute($caller->id, 999999, CatalogItemType::Product, 10_000);
    }

    public function test_scenario_chargesCostGate(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $counterparty = $this->verifiedBusiness('Counterparty Co');
        $before = app(GetCreditBalanceAction::class)->execute($caller->id)->balance;

        app(SimulateNegotiationScenarioAction::class)->execute($caller->id, $counterparty->id, CatalogItemType::Product, 10_000);

        $after = app(GetCreditBalanceAction::class)->execute($caller->id)->balance;
        $this->assertSame(5, $before - $after);
    }

    public function test_scenario_noHistory_returnsNullLikelihood(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $counterparty = $this->verifiedBusiness('Counterparty Co');

        $result = app(SimulateNegotiationScenarioAction::class)->execute($caller->id, $counterparty->id, CatalogItemType::Product, 10_000);

        $this->assertNull($result['estimatedAcceptanceLikelihood']);
    }

    public function test_scenario_priceAtOrBelowBaseline_boostsLikelihoodAboveBaseRate(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $accepted = app(InitiateNegotiationAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 1, new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null));
        app(AcceptDealAction::class)->execute($accepted->id, $buyer->id);

        $result = app(SimulateNegotiationScenarioAction::class)->execute($buyer->id, $seller->id, CatalogItemType::Product, 10_000);

        $this->assertSame(1.0, $result['baseAcceptanceRate']);
        $this->assertSame(10_000, $result['baselineAverageUnitAmount']);
        // baseRate 1.0 * 1.2 capped at 1.0
        $this->assertSame(1.0, $result['estimatedAcceptanceLikelihood']);
    }

    public function test_scenario_priceAboveBaseline_reducesLikelihood(): void
    {
        $buyerA = $this->verifiedBusiness('Buyer A Co');
        $buyerB = $this->verifiedBusiness('Buyer B Co');
        $seller = $this->verifiedBusiness('Seller Co');

        $accepted = app(InitiateNegotiationAction::class)->execute($buyerA->id, $seller->id, CatalogItemType::Product, 1, new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null));
        app(AcceptDealAction::class)->execute($accepted->id, $buyerA->id);

        $rejected = app(InitiateNegotiationAction::class)->execute($buyerB->id, $seller->id, CatalogItemType::Product, 2, new NegotiationTerms(Money::fromAmount(10_000, 'IRT'), 1, null));
        app(RejectDealAction::class)->execute($rejected->id, $seller->id, null);

        // baseRate = 1 accepted / 2 total = 0.5
        $result = app(SimulateNegotiationScenarioAction::class)->execute($buyerA->id, $seller->id, CatalogItemType::Product, 20_000);

        $this->assertSame(0.5, $result['baseAcceptanceRate']);
        // 20,000 is 100% above the 10,000 baseline -> percentAbove capped at 1.0 -> likelihood 0
        $this->assertSame(0.0, $result['estimatedAcceptanceLikelihood']);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}
