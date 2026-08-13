<?php

namespace App\Domains\Nexus\Holding\Application\Actions;

use App\Domains\Nexus\Holding\Application\DTOs\HoldingData;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;

/**
 * Resolves "the Holding this Business is part of, if any" — whether the
 * Business administers it (parent) or was invited into it (subsidiary,
 * Active only; a still-Invited business has no Holding to view yet, it has
 * a pending invitation instead — see ListHoldingInvitationsForBusinessAction).
 */
final class GetMyHoldingAction
{
    public function __construct(
        private readonly HoldingRepositoryInterface $holdings,
        private readonly HoldingSubsidiaryRepositoryInterface $subsidiaries,
        private readonly GetHoldingAction $getHolding,
    ) {
    }

    public function execute(int $businessId): ?HoldingData
    {
        $asParent = $this->holdings->findByParentBusinessId($businessId);

        if ($asParent) {
            return $this->getHolding->execute($asParent->id());
        }

        $membership = $this->subsidiaries->findActiveOrInvitedByBusinessId($businessId);

        if (! $membership || $membership->status()->value !== 'active') {
            return null;
        }

        return $this->getHolding->execute($membership->holdingId());
    }
}
