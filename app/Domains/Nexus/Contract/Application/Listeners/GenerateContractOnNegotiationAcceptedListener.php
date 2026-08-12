<?php

namespace App\Domains\Nexus\Contract\Application\Listeners;

use App\Domains\Nexus\Contract\Application\Actions\GenerateContractAction;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationWasAccepted;

/**
 * "تولید خودکار قرارداد از روی negotiation" — reacts to Negotiation's own
 * NegotiationWasAccepted event rather than AcceptDealAction/
 * ApprovePendingNegotiationAction calling into the Contract domain
 * directly (Inter-Module Communication, docs/modules.md), the same
 * Event Driven pattern CreateAgentOnBusinessVerifiedListener already
 * established in Phase 1.
 *
 * Charges `contract.generate` (Phase 3/M2's CostGate) against the
 * initiator's Business — the event carries only the Negotiation entity,
 * which never records which specific accept()/ApprovePendingNegotiationAction
 * call triggered it (either party's Agent, or a human approving a
 * pending one), so the initiator is charged deterministically regardless
 * of path. A documented simplification, not an oversight — splitting this
 * cost between both parties is a natural follow-up, not requested yet
 * (same style as the "either party can resolve Pending Approval" gap
 * Phase 2/M4 already documented).
 */
final class GenerateContractOnNegotiationAcceptedListener
{
    public function __construct(
        private readonly GenerateContractAction $generateContract,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    public function handle(NegotiationWasAccepted $event): void
    {
        $this->costGate->execute(
            $event->negotiation->initiatorBusinessId(),
            'contract.generate',
            $event->negotiation->id(),
        );

        $this->generateContract->execute($event->negotiation);
    }
}
