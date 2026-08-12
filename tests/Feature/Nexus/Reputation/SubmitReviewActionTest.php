<?php

namespace Tests\Feature\Nexus\Reputation;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Application\Actions\ReleaseEscrowAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Domains\Nexus\Reputation\Application\Actions\SubmitReviewAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SubmitReviewActionTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    /**
     * @return array{0: BusinessData, 1: BusinessData, 2: int}
     */
    private function releasedEscrowNegotiation(): array
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(ReleaseEscrowAction::class)->execute($negotiation->id, $buyer->id);

        return [$buyer, $seller, $negotiation->id];
    }

    public function test_execute_byBuyer_createsReviewOfSeller(): void
    {
        [$buyer, $seller, $negotiationId] = $this->releasedEscrowNegotiation();

        $review = app(SubmitReviewAction::class)->execute($negotiationId, $buyer->id, 5, 'excellent');

        $this->assertSame($buyer->id, $review->reviewerBusinessId);
        $this->assertSame($seller->id, $review->revieweeBusinessId);
        $this->assertSame(5, $review->rating);
        $this->assertSame('published', $review->status);
    }

    public function test_execute_bothPartiesCanReviewEachOther(): void
    {
        [$buyer, $seller, $negotiationId] = $this->releasedEscrowNegotiation();

        $buyerReview = app(SubmitReviewAction::class)->execute($negotiationId, $buyer->id, 5, null);
        $sellerReview = app(SubmitReviewAction::class)->execute($negotiationId, $seller->id, 4, null);

        $this->assertSame($seller->id, $buyerReview->revieweeBusinessId);
        $this->assertSame($buyer->id, $sellerReview->revieweeBusinessId);
    }

    public function test_execute_beforeEscrowReleased_throws(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);

        $this->expectException(InvalidArgumentException::class);

        app(SubmitReviewAction::class)->execute($negotiation->id, $buyer->id, 5, null);
    }

    public function test_execute_twiceByTheSameReviewer_throws(): void
    {
        [$buyer, , $negotiationId] = $this->releasedEscrowNegotiation();
        app(SubmitReviewAction::class)->execute($negotiationId, $buyer->id, 5, null);

        $this->expectException(InvalidArgumentException::class);

        app(SubmitReviewAction::class)->execute($negotiationId, $buyer->id, 3, null);
    }

    public function test_execute_byNonParty_throws(): void
    {
        [, , $negotiationId] = $this->releasedEscrowNegotiation();
        $outsider = $this->verifiedBusiness('Outsider Co');

        $this->expectException(InvalidArgumentException::class);

        app(SubmitReviewAction::class)->execute($negotiationId, $outsider->id, 5, null);
    }
}
