<?php

namespace App\Domains\Nexus\Reputation\Application\Actions;

use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Contract\Domain\ValueObjects\EscrowStatus;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Reputation\Application\DTOs\ReviewData;
use App\Domains\Nexus\Reputation\Domain\Entities\Review;
use App\Domains\Nexus\Reputation\Domain\Repositories\ReviewRepositoryInterface;
use InvalidArgumentException;

/**
 * Gated on EscrowStatus::Released (EscrowWasReleased), not merely
 * NegotiationWasAccepted — a signed deal that never actually got
 * delivered/confirmed has nothing honest to review yet. `revieweeBusinessId`
 * is always the Negotiation's *other* party — a Business can never target
 * a review at anyone but the counterparty on that specific deal. The
 * unique DB index (negotiation_id, reviewer_business_id) is the hard
 * backstop; the explicit findByNegotiationAndReviewer() check here exists
 * to fail with a clear domain message instead of a raw constraint
 * violation, the same "check before you'd hit the DB error" convention
 * RecordReferralSignupAction (Phase 5) already follows.
 */
final class SubmitReviewAction
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
        private readonly NegotiationRepositoryInterface $negotiations,
        private readonly EscrowRepositoryInterface $escrows,
    ) {
    }

    public function execute(int $negotiationId, int $reviewerBusinessId, int $rating, ?string $comment = null): ReviewData
    {
        $negotiation = $this->negotiations->findById($negotiationId);

        if (! $negotiation) {
            throw new InvalidArgumentException("Negotiation [{$negotiationId}] does not exist.");
        }

        if (! $negotiation->isParty($reviewerBusinessId)) {
            throw new InvalidArgumentException("Business [{$reviewerBusinessId}] is not a party to this Negotiation.");
        }

        $escrow = $this->escrows->findByNegotiationId($negotiationId);

        if (! $escrow || $escrow->status() !== EscrowStatus::Released) {
            throw new InvalidArgumentException("Negotiation [{$negotiationId}] has no completed (released) deal to review yet.");
        }

        if ($this->reviews->findByNegotiationAndReviewer($negotiationId, $reviewerBusinessId)) {
            throw new InvalidArgumentException("Business [{$reviewerBusinessId}] already reviewed Negotiation [{$negotiationId}].");
        }

        $review = Review::submit(
            negotiationId: $negotiationId,
            reviewerBusinessId: $reviewerBusinessId,
            revieweeBusinessId: $negotiation->otherParty($reviewerBusinessId),
            rating: $rating,
            comment: $comment,
        );

        return ReviewData::fromEntity($this->reviews->save($review));
    }
}
