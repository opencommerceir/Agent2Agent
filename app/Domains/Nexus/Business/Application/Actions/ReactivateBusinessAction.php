<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Entities\SuspensionRecord;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionRecordRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionTrigger;
use InvalidArgumentException;

/**
 * Admin-only (Dashboard, core `auth`/`admin` guard, never `business.auth`)
 * — either a direct admin decision or the outcome of
 * ResolveSuspensionAppealAction approving an appeal; always
 * SuspensionTrigger::Admin (a human always makes the call to lift a
 * suspension, even Phase 6/M4's own auto-suspension never auto-reverses
 * itself).
 */
final class ReactivateBusinessAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly SuspensionRecordRepositoryInterface $suspensionRecords,
    ) {
    }

    public function execute(int $businessId, string $reason): BusinessData
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $business->reactivate();
        $business = $this->businesses->save($business);

        $this->suspensionRecords->save(SuspensionRecord::record(
            businessId: $businessId,
            action: SuspensionAction::Reactivated,
            reason: $reason,
            triggeredBy: SuspensionTrigger::Admin,
        ));

        return BusinessData::fromEntity($business);
    }
}
