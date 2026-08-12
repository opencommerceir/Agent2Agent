<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Contract\Application\DTOs\EscrowData;
use App\Domains\Nexus\Contract\Domain\Events\EscrowWasReleased;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * "Confirm Delivery" on the Live Negotiation Viewer — Held -> Released.
 * Restricted to the Negotiation's initiator: every InitiateNegotiationAction
 * caller across this codebase (Marketplace-discovered proposals, Coalition's
 * CloseCoalitionAction) uses initiator-as-buyer/counterparty-as-seller, so
 * the initiator is the party who actually received the goods/services and
 * can honestly confirm delivery — the seller confirming its own delivery
 * to itself was the real risk in the old either-party check, the same
 * "known limitation" Phase 2/M4 and Phase 3/M4 both flagged for
 * tightening. DisputeEscrowAction stays either-party: raising a concern is
 * legitimately something either side can do.
 */
final class ReleaseEscrowAction
{
    public function __construct(
        private readonly EscrowRepositoryInterface $escrows,
        private readonly NegotiationRepositoryInterface $negotiations,
    ) {
    }

    public function execute(int $negotiationId, int $actingBusinessId): EscrowData
    {
        $escrow = $this->escrows->findByNegotiationId($negotiationId);

        if (! $escrow) {
            throw new InvalidArgumentException("No Escrow exists for Negotiation [{$negotiationId}].");
        }

        $negotiation = $this->negotiations->findById($negotiationId);

        if (! $negotiation || $negotiation->initiatorBusinessId() !== $actingBusinessId) {
            throw new InvalidArgumentException("Business [{$actingBusinessId}] is not the buyer on this Escrow's Negotiation.");
        }

        $escrow->release();
        $escrow = $this->escrows->save($escrow);

        Event::dispatch(new EscrowWasReleased($escrow));

        return EscrowData::fromEntity($escrow);
    }
}
