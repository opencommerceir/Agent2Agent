<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Growth\Application\DTOs\CoalitionData;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionMemberRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\ValueObjects\CoalitionStatus;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use InvalidArgumentException;

/**
 * Organizer-only. Once at least minParticipants have joined, aggregates
 * every member's committed quantity into one bulk NegotiationTerms (at the
 * coalition's own discounted unit price — Coalition::discountedUnitPrice())
 * and opens exactly one real Negotiation with the target supplier via the
 * existing InitiateNegotiationAction (Extend, Don't Rebuild — no parallel
 * "bulk deal" mechanism). The CostGate for opening that Negotiation
 * (nexus.negotiation.propose) is charged automatically — it already lives
 * inside InitiateNegotiationAction itself (Phase 3/M2), not duplicated
 * here. Whether the supplier actually accepts the bulk discount is entirely
 * that Negotiation's own normal propose/counter/accept/reject flow from
 * here on; this Action's only remaining job is recording which Negotiation
 * this Coalition became (CompleteCoalitionOnNegotiationAcceptedListener
 * closes the loop later).
 */
final class CloseCoalitionAction
{
    public function __construct(
        private readonly CoalitionRepositoryInterface $coalitions,
        private readonly CoalitionMemberRepositoryInterface $members,
        private readonly InitiateNegotiationAction $initiateNegotiation,
    ) {
    }

    public function execute(int $coalitionId, int $actingBusinessId): CoalitionData
    {
        $coalition = $this->coalitions->findById($coalitionId);

        if (! $coalition) {
            throw new InvalidArgumentException("Coalition [{$coalitionId}] does not exist.");
        }

        if ($actingBusinessId !== $coalition->organizerBusinessId()) {
            throw new InvalidArgumentException('Only the organizer can close this coalition.');
        }

        if ($coalition->status() !== CoalitionStatus::Forming) {
            throw new InvalidArgumentException("Coalition [{$coalitionId}] is not open for closing.");
        }

        $members = $this->members->findByCoalitionId($coalitionId);

        if (count($members) < $coalition->minParticipants()) {
            throw new InvalidArgumentException(
                "Coalition [{$coalitionId}] has [".count($members)."] member(s), needs at least [{$coalition->minParticipants()}]."
            );
        }

        $totalQuantity = array_sum(array_map(fn ($member) => $member->quantity(), $members));

        $negotiation = $this->initiateNegotiation->execute(
            initiatorBusinessId: $coalition->organizerBusinessId(),
            counterpartyBusinessId: $coalition->targetBusinessId(),
            catalogItemType: $coalition->catalogItemType(),
            catalogItemId: $coalition->catalogItemId(),
            terms: new NegotiationTerms(
                $coalition->discountedUnitPrice(),
                $totalQuantity,
                sprintf('Group buying coalition of %d businesses requesting a %g%% bulk discount.', count($members), $coalition->discountPercent()),
            ),
        );

        $coalition->startNegotiating($negotiation->id);
        $coalition = $this->coalitions->save($coalition);

        return CoalitionData::fromEntity($coalition, $members);
    }
}
