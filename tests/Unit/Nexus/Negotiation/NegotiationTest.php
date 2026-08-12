<?php

namespace Tests\Unit\Nexus\Negotiation;

use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation;
use App\Domains\Nexus\Negotiation\Domain\Exceptions\InvalidNegotiationStateException;
use App\Domains\Nexus\Negotiation\Domain\Exceptions\NegotiationRoundLimitExceededException;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationStatus;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database.
 * Negotiation is framework-free by design (Domain Layer Rules).
 */
class NegotiationTest extends TestCase
{
    private function terms(int $amount = 100000): NegotiationTerms
    {
        return new NegotiationTerms(Money::fromAmount($amount, 'IRT'), 1, null);
    }

    private function propose(int $maxRounds = 5): Negotiation
    {
        return Negotiation::propose(1, 10, 2, 20, CatalogItemType::Product, 5, $this->terms(), $maxRounds);
    }

    public function test_propose_createsWithProposedStatusAndRoundOne(): void
    {
        $negotiation = $this->propose();

        $this->assertNull($negotiation->id());
        $this->assertSame(NegotiationStatus::Proposed, $negotiation->status());
        $this->assertSame(1, $negotiation->roundCount());
    }

    public function test_counter_transitionsToCounteredAndIncrementsRound(): void
    {
        $negotiation = $this->propose();

        $negotiation->counter($this->terms(90000));

        $this->assertSame(NegotiationStatus::Countered, $negotiation->status());
        $this->assertSame(2, $negotiation->roundCount());
        $this->assertSame(90000, $negotiation->currentTerms()->price()->amount());
    }

    public function test_counter_beyondMaxRounds_throwsRoundLimitException(): void
    {
        $negotiation = $this->propose(maxRounds: 2);
        $negotiation->counter($this->terms(90000)); // round 2, still allowed (2 < 2 is false... )

        $this->expectException(NegotiationRoundLimitExceededException::class);

        $negotiation->counter($this->terms(80000));
    }

    public function test_accept_fromProposed_transitionsToAccepted(): void
    {
        $negotiation = $this->propose();

        $negotiation->accept();

        $this->assertSame(NegotiationStatus::Accepted, $negotiation->status());
    }

    public function test_accept_fromCountered_transitionsToAccepted(): void
    {
        $negotiation = $this->propose();
        $negotiation->counter($this->terms(90000));

        $negotiation->accept();

        $this->assertSame(NegotiationStatus::Accepted, $negotiation->status());
    }

    public function test_reject_setsRejectedStatusAndReason(): void
    {
        $negotiation = $this->propose();

        $negotiation->reject('too expensive');

        $this->assertSame(NegotiationStatus::Rejected, $negotiation->status());
        $this->assertSame('too expensive', $negotiation->rejectionReason());
    }

    public function test_requestApproval_thenAccept_reachesAccepted(): void
    {
        $negotiation = $this->propose();

        $negotiation->requestApproval(1);
        $this->assertSame(NegotiationStatus::PendingApproval, $negotiation->status());
        $this->assertSame(1, $negotiation->pendingApprovalBusinessId());

        $negotiation->accept();
        $this->assertSame(NegotiationStatus::Accepted, $negotiation->status());
    }

    public function test_requestApproval_thenReject_reachesRejected(): void
    {
        $negotiation = $this->propose();

        $negotiation->requestApproval(1);
        $negotiation->reject('over authority limit');

        $this->assertSame(NegotiationStatus::Rejected, $negotiation->status());
    }

    public function test_pendingApproval_cannotBeCountered(): void
    {
        $negotiation = $this->propose();
        $negotiation->requestApproval(1);

        $this->expectException(InvalidNegotiationStateException::class);

        $negotiation->counter($this->terms(90000));
    }

    public function test_accept_onAlreadyAcceptedNegotiation_throwsInvalidStateException(): void
    {
        $negotiation = $this->propose();
        $negotiation->accept();

        $this->expectException(InvalidNegotiationStateException::class);

        $negotiation->accept();
    }

    public function test_reject_onAlreadyRejectedNegotiation_throwsInvalidStateException(): void
    {
        $negotiation = $this->propose();
        $negotiation->reject();

        $this->expectException(InvalidNegotiationStateException::class);

        $negotiation->reject();
    }

    public function test_isParty_and_otherParty(): void
    {
        $negotiation = $this->propose();

        $this->assertTrue($negotiation->isParty(1));
        $this->assertTrue($negotiation->isParty(2));
        $this->assertFalse($negotiation->isParty(3));
        $this->assertSame(2, $negotiation->otherParty(1));
        $this->assertSame(1, $negotiation->otherParty(2));
    }
}
