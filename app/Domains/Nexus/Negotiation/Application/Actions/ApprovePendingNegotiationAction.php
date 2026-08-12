<?php

namespace App\Domains\Nexus\Negotiation\Application\Actions;

use App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationData;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationWasAccepted;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * The only way a Negotiation leaves PendingApproval into Accepted — a
 * human (the Business owner, via the web viewer, M7) decided, not an
 * Agent. Deliberately does NOT reuse AcceptDealAction: that Action
 * re-checks authority_limits and would route straight back into
 * requestApproval(), which the entity's own ALLOWED_TRANSITIONS table
 * correctly refuses (PendingApproval -> PendingApproval isn't a legal
 * transition) — a human's approval must bypass the gate entirely, not
 * re-evaluate it.
 *
 * Only the Business whose own accept() attempt exceeded its Agent's
 * authority_limits (Negotiation::pendingApprovalBusinessId(), set by
 * AcceptDealAction at requestApproval() time) may resolve the pause — not
 * merely any party. The counterparty proposed/accepted the deal on the
 * terms it already wanted; it isn't the one whose authority was found
 * insufficient, so it has nothing to approve here.
 */
final class ApprovePendingNegotiationAction
{
    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiations,
    ) {
    }

    public function execute(int $negotiationId, int $approvingBusinessId): NegotiationData
    {
        $negotiation = $this->negotiations->findById($negotiationId);

        if (! $negotiation) {
            throw new InvalidArgumentException("Negotiation [{$negotiationId}] does not exist.");
        }

        if ($negotiation->pendingApprovalBusinessId() !== $approvingBusinessId) {
            throw new InvalidArgumentException("Business [{$approvingBusinessId}] is not the party awaiting approval on this Negotiation.");
        }

        $negotiation->accept();
        $negotiation = $this->negotiations->save($negotiation);

        Event::dispatch(new NegotiationWasAccepted($negotiation));

        return NegotiationData::fromEntity($negotiation);
    }
}
