<?php

namespace App\Domains\Nexus\Contract\Application\Listeners;

use App\Domains\Nexus\Contract\Application\Actions\GenerateContractAction;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationWasAccepted;

/**
 * "تولید خودکار قرارداد از روی negotiation" — reacts to Negotiation's own
 * NegotiationWasAccepted event rather than AcceptDealAction/
 * ApprovePendingNegotiationAction calling into the Contract domain
 * directly (Inter-Module Communication, docs/modules.md), the same
 * Event Driven pattern CreateAgentOnBusinessVerifiedListener already
 * established in Phase 1.
 */
final class GenerateContractOnNegotiationAcceptedListener
{
    public function __construct(
        private readonly GenerateContractAction $generateContract,
    ) {
    }

    public function handle(NegotiationWasAccepted $event): void
    {
        $this->generateContract->execute($event->negotiation);
    }
}
