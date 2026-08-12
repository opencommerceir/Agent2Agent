<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Contract\Application\DTOs\EscrowData;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use InvalidArgumentException;

/**
 * "Confirm Delivery" on the Live Negotiation Viewer — Held -> Released.
 * Either party may release in this phase (Escrow doesn't track which side
 * is the deliverer vs. the receiver yet) — the same documented,
 * deliberate-not-oversight gap Phase 2/M4 already established for
 * Pending Approval.
 */
final class ReleaseEscrowAction
{
    public function __construct(
        private readonly EscrowRepositoryInterface $escrows,
    ) {
    }

    public function execute(int $negotiationId, int $actingBusinessId): EscrowData
    {
        $escrow = $this->escrows->findByNegotiationId($negotiationId);

        if (! $escrow) {
            throw new InvalidArgumentException("No Escrow exists for Negotiation [{$negotiationId}].");
        }

        if (! $escrow->isParty($actingBusinessId)) {
            throw new InvalidArgumentException("Business [{$actingBusinessId}] is not a party to this Escrow.");
        }

        $escrow->release();
        $escrow = $this->escrows->save($escrow);

        return EscrowData::fromEntity($escrow);
    }
}
