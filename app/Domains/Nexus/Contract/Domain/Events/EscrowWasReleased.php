<?php

namespace App\Domains\Nexus\Contract\Domain\Events;

use App\Domains\Nexus\Contract\Domain\Entities\Escrow;

/**
 * Domain event: a fact that already happened (Event Conventions) — the
 * buyer confirmed delivery, so the deal is genuinely complete (paid and
 * fulfilled), not merely agreed the way NegotiationWasAccepted is. This is
 * the honest trigger point for Phase 6's Reviews & Ratings: a review
 * prompted here reflects a deal that actually happened end-to-end, not
 * just a signed contract that could still fall through.
 */
final class EscrowWasReleased
{
    public function __construct(
        public readonly Escrow $escrow,
    ) {
    }
}
