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
 * Admin-only when $triggeredBy is Admin (Dashboard, core `auth`/`admin`
 * guard); called with AutoFraudDetection from DetectFraudSignalsAction
 * (Reputation domain) — both paths funnel through the same Action so
 * exactly one place ever flips Business::suspend() and records why.
 */
final class SuspendBusinessAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly SuspensionRecordRepositoryInterface $suspensionRecords,
    ) {
    }

    public function execute(int $businessId, string $reason, SuspensionTrigger $triggeredBy = SuspensionTrigger::Admin): BusinessData
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $business->suspend();
        $business = $this->businesses->save($business);

        $this->suspensionRecords->save(SuspensionRecord::record(
            businessId: $businessId,
            action: SuspensionAction::Suspended,
            reason: $reason,
            triggeredBy: $triggeredBy,
        ));

        return BusinessData::fromEntity($business);
    }
}
