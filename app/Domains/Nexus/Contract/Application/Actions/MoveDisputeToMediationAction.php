<?php

namespace App\Domains\Nexus\Contract\Application\Actions;

use App\Domains\Nexus\Contract\Application\DTOs\DisputeCaseData;
use App\Domains\Nexus\Contract\Domain\Repositories\DisputeCaseRepositoryInterface;
use InvalidArgumentException;

/**
 * Admin-only (Dashboard, core `auth`/`admin` guard, never `business.auth`)
 * — marks that an admin/mediator is actively engaged, Open -> Mediation.
 * A visibility marker more than a hard gate (ArbitrateDisputeAction can
 * resolve directly from Open too, per DisputeCase's own ALLOWED_TRANSITIONS).
 */
final class MoveDisputeToMediationAction
{
    public function __construct(
        private readonly DisputeCaseRepositoryInterface $disputeCases,
    ) {
    }

    public function execute(int $disputeCaseId): DisputeCaseData
    {
        $disputeCase = $this->disputeCases->findById($disputeCaseId);

        if (! $disputeCase) {
            throw new InvalidArgumentException("DisputeCase [{$disputeCaseId}] does not exist.");
        }

        $disputeCase->moveToMediation();

        return DisputeCaseData::fromEntity($this->disputeCases->save($disputeCase));
    }
}
