<?php

namespace App\Domains\Nexus\Negotiation\Domain\Events;

use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation;
use App\Domains\Nexus\Negotiation\Domain\Entities\NegotiationMessage;

/**
 * Domain event: a fact that already happened (Event Conventions). Fired
 * only for message types that leave the ball in the other party's court —
 * Proposal and Counter, never Accept/Reject (those are terminal; Accept
 * already fires its own NegotiationWasAccepted). AutoRespondToNegotiation
 * Listener (Application layer) is the one real listener today, deciding
 * whether the receiving party's own Agent should react on its own.
 */
final class NegotiationMessageWasRecorded
{
    public function __construct(
        public readonly Negotiation $negotiation,
        public readonly NegotiationMessage $message,
    ) {
    }
}
