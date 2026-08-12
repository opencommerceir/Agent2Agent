<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Contract\Application\DTOs\EscrowData;
use App\Domains\Nexus\Contract\Domain\Repositories\EscrowRepositoryInterface;
use InvalidArgumentException;

/**
 * Admin-only (Dashboard, core `auth` guard — never business.auth) —
 * Disputed -> Refunded. Since Nexus never actually moved real money
 * between the two Businesses (Escrow is a state-tracking layer, see the
 * entity's own docblock), this only records the resolution for the
 * Revenue Dashboard (Phase 3/M6); no real fund transfer happens here.
 */
final class RefundEscrowAction
{
    public function __construct(
        private readonly EscrowRepositoryInterface $escrows,
    ) {
    }

    public function execute(int $escrowId): EscrowData
    {
        $escrow = $this->escrows->findById($escrowId);

        if (! $escrow) {
            throw new InvalidArgumentException("Escrow [{$escrowId}] does not exist.");
        }

        $escrow->refund();
        $escrow = $this->escrows->save($escrow);

        return EscrowData::fromEntity($escrow);
    }
}
