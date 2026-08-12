<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Contract\Application\DTOs\DisputeCaseData;
use App\Domains\Nexus\Contract\Domain\Entities\DisputeCase;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;

/**
 * Called only from OpenDisputeCaseOnEscrowDisputedListener (event-driven,
 * on EscrowWasDisputed) — never directly by a controller/capability
 * handler, the same "auto-provisioning listener" shape
 * GrantStartingCreditsOnBusinessVerifiedListener already establishes.
 */
final class OpenDisputeCaseAction
{
    public function __construct(
        private readonly DisputeCaseRepositoryInterface $disputeCases,
    ) {
    }

    public function execute(
        int $escrowId,
        int $negotiationId,
        int $businessAId,
        int $businessBId,
        int $openedByBusinessId,
        ?string $reason,
    ): DisputeCaseData {
        $disputeCase = DisputeCase::open(
            escrowId: $escrowId,
            negotiationId: $negotiationId,
            businessAId: $businessAId,
            businessBId: $businessBId,
            openedByBusinessId: $openedByBusinessId,
            reason: $reason,
        );

        return DisputeCaseData::fromEntity($this->disputeCases->save($disputeCase));
    }
}
