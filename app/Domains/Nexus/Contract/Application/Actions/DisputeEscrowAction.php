<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Contract\Application\DTOs\EscrowData;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use InvalidArgumentException;

/**
 * Either party flags a Held Escrow as Disputed — Held -> Disputed. No
 * arbitration/evidence workflow exists yet (that's Phase 6 Trust &
 * Reputation's "Dispute Resolution" territory, docs/nexus-roadmap.md) —
 * a documented, deliberate gap, not an oversight. Resolution today is
 * RefundEscrowAction, an admin-only manual action.
 */
final class DisputeEscrowAction
{
    public function __construct(
        private readonly EscrowRepositoryInterface $escrows,
    ) {
    }

    public function execute(int $negotiationId, int $actingBusinessId, ?string $reason = null): EscrowData
    {
        $escrow = $this->escrows->findByNegotiationId($negotiationId);

        if (! $escrow) {
            throw new InvalidArgumentException("No Escrow exists for Negotiation [{$negotiationId}].");
        }

        if (! $escrow->isParty($actingBusinessId)) {
            throw new InvalidArgumentException("Business [{$actingBusinessId}] is not a party to this Escrow.");
        }

        $escrow->dispute($reason);
        $escrow = $this->escrows->save($escrow);

        return EscrowData::fromEntity($escrow);
    }
}
