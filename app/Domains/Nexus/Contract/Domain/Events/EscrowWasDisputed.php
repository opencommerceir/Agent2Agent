<?php

namespace App\Domains\Nexus\Contract\Domain\Events;

use App\Domains\Nexus\Contract\Domain\Entities\Escrow;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Phase 6/M3's Dispute domain listens for this to auto-open a DisputeCase
 * (evidence/mediation/arbitration) — event-driven, same rule
 * ContractWasGenerated -> HoldEscrowOnContractGeneratedListener already
 * established, not a direct call from DisputeEscrowAction into the
 * Dispute domain.
 */
final class EscrowWasDisputed
{
    public function __construct(
        public readonly Escrow $escrow,
        public readonly int $openedByBusinessId,
    ) {
    }
}
