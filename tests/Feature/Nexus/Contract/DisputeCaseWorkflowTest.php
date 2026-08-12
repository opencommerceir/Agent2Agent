<?php

namespace Tests\Feature\Nexus\Contract;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Contract\Application\Actions\ArbitrateDisputeAction;
use App\Domains\Nexus\Contract\Application\Actions\MoveDisputeToMediationAction;
use App\Domains\Nexus\Contract\Application\Actions\SubmitDisputeEvidenceAction;
use App\Domains\Nexus\Contract\Application\Actions\DisputeEscrowAction;
use App\Domains\Nexus\Contract\Domain\Events\EscrowWasReleased;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Domains\Nexus\Reputation\Application\Actions\SubmitReviewAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The real event chain, not Event::fake() throughout — DisputeEscrowAction
 * -> EscrowWasDisputed -> OpenDisputeCaseOnEscrowDisputedListener ->
 * a real DisputeCase row, the same rigor HoldEscrowOnContractGeneratedListenerTest
 * already applies to Contract -> Escrow.
 */
class DisputeCaseWorkflowTest extends TestCase
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
    private function disputedNegotiation(): array
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(DisputeEscrowAction::class)->execute($negotiation->id, $buyer->id, 'never delivered');

        return [$buyer, $seller, $negotiation->id];
    }

    public function test_disputeEscrow_autoOpensDisputeCase(): void
    {
        [$buyer, , $negotiationId] = $this->disputedNegotiation();
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);

        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());

        $this->assertNotNull($disputeCase);
        $this->assertSame('open', $disputeCase->status()->value);
        $this->assertSame($buyer->id, $disputeCase->openedByBusinessId());
        $this->assertSame('never delivered', $disputeCase->reason());
    }

    public function test_submitDisputeEvidence_appendsNoteFromEitherParty(): void
    {
        [$buyer, $seller, $negotiationId] = $this->disputedNegotiation();
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);
        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());

        app(SubmitDisputeEvidenceAction::class)->execute($disputeCase->id(), $buyer->id, 'photo of empty box');
        $result = app(SubmitDisputeEvidenceAction::class)->execute($disputeCase->id(), $seller->id, 'tracking number XYZ');

        $this->assertCount(2, $result->evidence);
    }

    public function test_moveToMediation_transitionsStatus(): void
    {
        [, , $negotiationId] = $this->disputedNegotiation();
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);
        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());

        $result = app(MoveDisputeToMediationAction::class)->execute($disputeCase->id());

        $this->assertSame('mediation', $result->status);
    }

    public function test_arbitrate_refundBuyer_transitionsEscrowToRefunded(): void
    {
        [, , $negotiationId] = $this->disputedNegotiation();
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);
        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());

        $result = app(ArbitrateDisputeAction::class)->execute($disputeCase->id(), 'refund_buyer');

        $this->assertSame('resolved', $result->status);
        $this->assertSame('refund_buyer', $result->resolution);
        $updatedEscrow = app(EscrowRepositoryInterface::class)->findById($escrow->id());
        $this->assertSame('refunded', $updatedEscrow->status()->value);
    }

    public function test_arbitrate_releaseSeller_transitionsEscrowToReleased_andDispatchesEscrowWasReleased(): void
    {
        Event::fake([EscrowWasReleased::class]);
        [, , $negotiationId] = $this->disputedNegotiation();
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);
        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());

        app(ArbitrateDisputeAction::class)->execute($disputeCase->id(), 'release_seller');

        $updatedEscrow = app(EscrowRepositoryInterface::class)->findById($escrow->id());
        $this->assertSame('released', $updatedEscrow->status()->value);
        Event::assertDispatched(EscrowWasReleased::class);
    }

    public function test_arbitrate_releaseSeller_makesReviewReachable(): void
    {
        [$buyer, , $negotiationId] = $this->disputedNegotiation();
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);
        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());
        app(ArbitrateDisputeAction::class)->execute($disputeCase->id(), 'release_seller');

        $review = app(SubmitReviewAction::class)->execute($negotiationId, $buyer->id, 3, 'resolved eventually');

        $this->assertSame('published', $review->status);
    }

    public function test_arbitrate_withUnknownResolution_throws(): void
    {
        [, , $negotiationId] = $this->disputedNegotiation();
        $escrow = app(EscrowRepositoryInterface::class)->findByNegotiationId($negotiationId);
        $disputeCase = app(DisputeCaseRepositoryInterface::class)->findByEscrowId($escrow->id());

        $this->expectException(InvalidArgumentException::class);

        app(ArbitrateDisputeAction::class)->execute($disputeCase->id(), 'bogus');
    }
}
