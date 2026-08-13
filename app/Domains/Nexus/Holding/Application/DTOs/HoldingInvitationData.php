<?php

namespace App\Domains\Nexus\Holding\Application\DTOs;

/**
 * Lighter-weight than HoldingData — just enough for a Business to see "you
 * have been invited to X" and decide, without pulling in the full
 * subsidiary roster.
 */
final class HoldingInvitationData
{
    public function __construct(
        public readonly int $subsidiaryId,
        public readonly int $holdingId,
        public readonly string $holdingNameEn,
        public readonly string $parentBusinessNameEn,
        public readonly string $invitedAt,
    ) {
    }
}
