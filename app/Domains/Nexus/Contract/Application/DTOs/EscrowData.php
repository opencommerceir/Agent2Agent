<?php

namespace App\Domains\Nexus\Contract\Application\DTOs;

use App\Domains\Nexus\Contract\Domain\Entities\Escrow;

/**
 * Structured data transfer for Escrow across layers. Represents data
 * only — no business logic (DTO Conventions).
 */
final class EscrowData
{
    public function __construct(
        public readonly int $id,
        public readonly int $contractId,
        public readonly int $negotiationId,
        public readonly int $grossAmount,
        public readonly string $currency,
        public readonly float $platformFeePercent,
        public readonly int $platformFeeAmount,
        public readonly int $netAmount,
        public readonly string $status,
        public readonly ?string $disputeReason,
    ) {
    }

    public static function fromEntity(Escrow $escrow): self
    {
        return new self(
            id: $escrow->id(),
            contractId: $escrow->contractId(),
            negotiationId: $escrow->negotiationId(),
            grossAmount: $escrow->grossAmount(),
            currency: $escrow->currency(),
            platformFeePercent: $escrow->platformFeePercent(),
            platformFeeAmount: $escrow->platformFeeAmount(),
            netAmount: $escrow->netAmount(),
            status: $escrow->status()->value,
            disputeReason: $escrow->disputeReason(),
        );
    }
}
