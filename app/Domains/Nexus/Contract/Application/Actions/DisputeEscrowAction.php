<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Contract\Application\DTOs\EscrowData;
use App\Domains\Nexus\Contract\Domain\Events\EscrowWasDisputed;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Either party flags a Held Escrow as Disputed — Held -> Disputed. Phase
 * 6/M3's Dispute domain listens for the EscrowWasDisputed event this
 * dispatches to auto-open the real evidence/mediation/arbitration
 * workflow (OpenDisputeCaseOnEscrowDisputedListener) — this Action itself
 * stays exactly the simple "either party can flag it" entry point it
 * always was, per its own original docblock.
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

        Event::dispatch(new EscrowWasDisputed($escrow, $actingBusinessId));

        return EscrowData::fromEntity($escrow);
    }
}
