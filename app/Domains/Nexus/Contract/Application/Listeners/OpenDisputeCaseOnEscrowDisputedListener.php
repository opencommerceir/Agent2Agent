<?php

namespace App\Domains\Nexus\Contract\Application\Listeners;

use App\Domains\Nexus\Contract\Application\Actions\OpenDisputeCaseAction;
use App\Domains\Nexus\Contract\Domain\Events\EscrowWasDisputed;

/**
 * Reacts to Escrow's own EscrowWasDisputed event rather than
 * DisputeEscrowAction calling into the Dispute workflow directly — same
 * event-driven shape HoldEscrowOnContractGeneratedListener already
 * established for Contract -> Escrow.
 */
final class OpenDisputeCaseOnEscrowDisputedListener
{
    public function __construct(
        private readonly OpenDisputeCaseAction $openDisputeCase,
    ) {
    }

    public function handle(EscrowWasDisputed $event): void
    {
        $this->openDisputeCase->execute(
            escrowId: $event->escrow->id(),
            negotiationId: $event->escrow->negotiationId(),
            businessAId: $event->escrow->businessAId(),
            businessBId: $event->escrow->businessBId(),
            openedByBusinessId: $event->openedByBusinessId,
            reason: $event->escrow->disputeReason(),
        );
    }
}
