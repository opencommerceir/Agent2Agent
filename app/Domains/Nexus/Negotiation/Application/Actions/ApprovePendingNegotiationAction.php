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
 * Either party may resolve a pending approval in this phase — Negotiation
 * doesn't track which specific side's authority_limits triggered the
 * pause, so this doesn't restrict to "only the side that would have
 * accepted." Narrowing that is a natural follow-up, not requested yet.
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

        if (! $negotiation->isParty($approvingBusinessId)) {
            throw new InvalidArgumentException("Business [{$approvingBusinessId}] is not a party to this Negotiation.");
        }

        $negotiation->accept();
        $negotiation = $this->negotiations->save($negotiation);

        Event::dispatch(new NegotiationWasAccepted($negotiation));

        return NegotiationData::fromEntity($negotiation);
    }
}
