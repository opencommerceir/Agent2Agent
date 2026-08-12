<?php

namespace App\Domains\Nexus\Negotiation\Domain\Events;

use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * M6's Contract domain listens for this to auto-generate the Contract —
 * event-driven, same rule Phase 1's BusinessWasVerified -> Agent
 * auto-creation already established, not a direct cross-domain call.
 */
final class NegotiationWasAccepted
{
    public function __construct(
        public readonly Negotiation $negotiation,
    ) {
    }
}
