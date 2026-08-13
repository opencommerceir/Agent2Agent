<?php

namespace App\Domains\Nexus\Developer\Domain\ValueObjects;

/**
 * The closed set of domain events a WebhookSubscription can subscribe to
 * — deliberately only events that already exist as real dispatched
 * events elsewhere in the codebase (NegotiationWasAccepted, Phase 2/M3;
 * EscrowWasReleased, Phase 6/M1; ContractWasGenerated, Phase 2/M6), same
 * "never invent a signal that isn't genuinely tracked" discipline
 * ReputationQuery's own response-time omission established. Grows
 * additively as new domain events prove useful to expose externally.
 */
enum WebhookEvent: string
{
    case NegotiationAccepted = 'negotiation.accepted';
    case EscrowReleased = 'escrow.released';
    case ContractGenerated = 'contract.generated';
}
