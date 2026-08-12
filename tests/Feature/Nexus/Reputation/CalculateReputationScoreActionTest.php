<?php

namespace Tests\Feature\Nexus\Reputation;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Application\Actions\ArbitrateDisputeAction;
use App\Domains\Nexus\Contract\Application\Actions\DisputeEscrowAction;
use App\Domains\Nexus\Contract\Application\Actions\ReleaseEscrowAction;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Application\Actions\RejectDealAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Domains\Nexus\Reputation\Application\Actions\CalculateReputationScoreAction;
use App\Domains\Nexus\Reputation\Application\Actions\SubmitReviewAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CalculateReputationScoreActionTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    public function test_execute_forFreshBusiness_returnsZeroScoreWithVerifiedBadgeOnly(): void
    {
        $business = $this->verifiedBusiness('Fresh Co');

        $result = app(CalculateReputationScoreAction::class)->execute($business->id);

        $this->assertSame(0, $result->score);
        $this->assertSame(0.0, $result->successRate);
        $this->assertSame(0, $result->reviewCount);
        $this->assertSame(['verified'], $result->badges);
    }

    public function test_execute_unverifiedBusiness_hasNoVerifiedBadge(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('نام Unverified', 'Unverified Co', BusinessType::Company, Industry::Technology);

        $result = app(CalculateReputationScoreAction::class)->execute($business->id);

        $this->assertSame([], $result->badges);
    }

    public function test_execute_afterAcceptedDeal_increasesSuccessRate(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $result = app(CalculateReputationScoreAction::class)->execute($buyer->id);

        $this->assertSame(1.0, $result->successRate);
    }

    public function test_execute_afterRejectedDeal_decreasesSuccessRate(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(RejectDealAction::class)->execute($negotiation->id, $seller->id, 'not interested');

        $result = app(CalculateReputationScoreAction::class)->execute($buyer->id);

        $this->assertSame(0.0, $result->successRate);
    }

    public function test_execute_withPublishedReviews_computesAverageAndComponent(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(ReleaseEscrowAction::class)->execute($negotiation->id, $buyer->id);
        app(SubmitReviewAction::class)->execute($negotiation->id, $buyer->id, 5, null);

        $result = app(CalculateReputationScoreAction::class)->execute($seller->id);

        $this->assertSame(1, $result->reviewCount);
        $this->assertSame(5.0, $result->averageRating);
        $this->assertSame(1, $result->completedDeals);
        $this->assertGreaterThan(0, $result->score);
    }

    public function test_execute_afterLostDispute_appliesPenalty(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(DisputeEscrowAction::class)->execute($negotiation->id, $buyer->id, 'never delivered');
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiation->id);
        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());
        // 'refund_buyer' rules against the seller.
        app(ArbitrateDisputeAction::class)->execute($disputeCase->id(), 'refund_buyer');

        $sellerScore = app(CalculateReputationScoreAction::class)->execute($seller->id);
        $buyerScore = app(CalculateReputationScoreAction::class)->execute($buyer->id);

        $this->assertSame(1, $sellerScore->disputesLost);
        $this->assertSame(0, $buyerScore->disputesLost);
        // successRate is still 1.0 for the seller (Accepted, per Negotiation
        // status) — only the dispute penalty should hold the score down.
        $this->assertLessThan($buyerScore->score, $sellerScore->score);
    }

    public function test_execute_forNonExistentBusiness_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CalculateReputationScoreAction::class)->execute(9999);
    }

    public function test_execute_topRatedBadge_requiresMinimumReviewsAndAverage(): void
    {
        $seller = $this->verifiedBusiness('Seller Co');

        for ($i = 0; $i < 5; $i++) {
            $buyer = $this->verifiedBusiness("Buyer {$i} Co");
            $negotiation = app(InitiateNegotiationAction::class)->execute(
                $buyer->id, $seller->id, CatalogItemType::Product, 1,
                new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
            );
            app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
            app(ReleaseEscrowAction::class)->execute($negotiation->id, $buyer->id);
            app(SubmitReviewAction::class)->execute($negotiation->id, $buyer->id, 5, null);
        }

        $result = app(CalculateReputationScoreAction::class)->execute($seller->id);

        $this->assertContains('top_rated', $result->badges);
    }
}
