<?php

namespace App\Domains\Nexus\Contract\Domain\Events;

use App\Domains\Nexus\Contract\Domain\Entities\Contract;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Phase 3/M4's Escrow listens for this to auto-hold the deal's value —
 * event-driven, same rule Phase 1's BusinessWasVerified -> Agent
 * auto-creation already established, not a direct call from
 * GenerateContractAction into the Escrow entity.
 */
final class ContractWasGenerated
{
    public function __construct(
        public readonly Contract $contract,
    ) {
    }
}
