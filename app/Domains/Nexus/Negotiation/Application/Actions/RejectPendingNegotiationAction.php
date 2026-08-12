<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use InvalidArgumentException;

/**
 * A human rejecting a Negotiation paused for approval — thin wrapper
 * over the same Negotiation::reject() RejectDealAction already uses
 * (legal from PendingApproval per the entity's own ALLOWED_TRANSITIONS),
 * kept as its own Action so the web viewer's "reject a pending approval"
 * button doesn't need to pretend it's an Agent acting via RejectDealAction.
 *
 * Restricted to Negotiation::pendingApprovalBusinessId() for the same
 * reason ApprovePendingNegotiationAction is — only the Business whose own
 * authority_limits triggered the pause has a decision to make here.
 */
final class RejectPendingNegotiationAction
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
    ) {
    }

    public function execute(int $negotiationId, int $rejectingBusinessId, ?string $reason = null): NegotiationData
    {
        $negotiation = $this->negotiations->findById($negotiationId);

        if (! $negotiation) {
            throw new InvalidArgumentException("Negotiation [{$negotiationId}] does not exist.");
        }

        if ($negotiation->pendingApprovalBusinessId() !== $rejectingBusinessId) {
            throw new InvalidArgumentException("Business [{$rejectingBusinessId}] is not the party awaiting approval on this Negotiation.");
        }

        $negotiation->reject($reason);
        $negotiation = $this->negotiations->save($negotiation);

        return NegotiationData::fromEntity($negotiation);
    }
}
