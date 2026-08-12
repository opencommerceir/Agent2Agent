<?php

namespace App\Domains\Nexus\Contract\Application\Listeners;

use App\Domains\Nexus\Contract\Application\Actions\HoldEscrowAction;
use App\Domains\Nexus\Contract\Domain\Events\ContractWasGenerated;

/**
 * Reacts to Contract's own ContractWasGenerated event rather than
 * GenerateContractAction calling into Escrow directly (Inter-Module
 * Communication, docs/modules.md) — same event-driven shape
 * GenerateContractOnNegotiationAcceptedListener already established for
 * Negotiation -> Contract.
 */
final class HoldEscrowOnContractGeneratedListener
{
    public function __construct(
        private readonly HoldEscrowAction $holdEscrow,
    ) {
    }

    public function handle(ContractWasGenerated $event): void
    {
        $this->holdEscrow->execute($event->contract);
    }
}
