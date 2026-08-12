<?php

namespace App\Domains\Nexus\Growth\Domain\ValueObjects;

/**
 * `Negotiating -> Completed` is closed by CompleteCoalitionOnNegotiationAcceptedListener
 * (Negotiation's own NegotiationWasAccepted event, Inter-Module
 * Communication). There is no `NegotiationWasRejected` event anywhere in
 * this codebase (Negotiation/M3's own documented gap) — a coalition whose
 * bulk negotiation gets rejected has no automatic signal to react to, so
 * `Negotiating -> Cancelled` stays open as a manual organizer escape hatch
 * (CancelCoalitionAction) rather than pretending an automatic transition
 * exists that the codebase can't actually detect.
 */
enum CoalitionStatus: string
{
    case Forming = 'forming';
    case Negotiating = 'negotiating';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
