<?php

namespace App\Domains\Nexus\Growth\Application\Listeners;

use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Events\NegotiationWasAccepted;

/**
 * NegotiationWasAccepted fires for every accepted Negotiation on the
 * platform, not just coalition-originated ones — this listener is a no-op
 * for the (overwhelming majority) case where findByNegotiationId() finds
 * nothing, exactly like every other Nexus listener that only reacts when
 * its own domain's row actually exists (e.g.
 * HoldEscrowOnContractGeneratedListener). Inter-Module Communication: reacts
 * to Negotiation's own event, never a direct call from Negotiation into
 * Growth.
 */
final class CompleteCoalitionOnNegotiationAcceptedListener
{
    public function __construct(
        private readonly CoalitionRepositoryInterface $coalitions,
    ) {
    }

    public function handle(NegotiationWasAccepted $event): void
    {
        $coalition = $this->coalitions->findByNegotiationId($event->negotiation->id());

        if (! $coalition) {
            return;
        }

        $coalition->complete();
        $this->coalitions->save($coalition);
    }
}
