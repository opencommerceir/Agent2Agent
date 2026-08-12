<?php

namespace App\Domains\Nexus\Reputation\Application\Actions;

use App\Domains\Nexus\Business\Application\Actions\SuspendBusinessAction;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionTrigger;
use App\Domains\Nexus\Reputation\Infrastructure\Queries\FraudSignalQuery;

/**
 * Runs hourly via DetectFraudSignalsCommand (Schedule::command() in
 * routes/console.php, the same real scheduling infrastructure
 * ExpireLoyaltyPointsCommand/ProcessDueSubscriptionsCommand already use)
 * — the admin Fraud queue's "Run detection now" button
 * (NexusFraudController::runDetection()) calls this same Action directly
 * for an on-demand check between scheduled runs. Idempotent:
 * already-suspended businesses are skipped, so re-running this
 * repeatedly never double-suspends or double-records.
 */
final class DetectFraudSignalsAction
{
    public function __construct(
        private readonly FraudSignalQuery $fraudSignals,
        private readonly BusinessRepositoryInterface $businesses,
        private readonly SuspendBusinessAction $suspendBusiness,
    ) {
    }

    /**
     * @return list<int> businessIds newly suspended by this run
     */
    public function execute(): array
    {
        $threshold = (int) config('nexus.platform.reputation.fraud.dispute_loss_threshold');
        $windowDays = (int) config('nexus.platform.reputation.fraud.dispute_loss_window_days');

        $flagged = $this->fraudSignals->businessesExceedingDisputeLossThreshold($threshold, $windowDays);
        $newlySuspended = [];

        foreach ($flagged as $businessId) {
            $business = $this->businesses->findById($businessId);

            if (! $business || ! $business->isActive()) {
                continue;
            }

            $this->suspendBusiness->execute(
                $businessId,
                "Auto-suspended: {$threshold}+ disputes ruled against this Business in the last {$windowDays} days.",
                SuspensionTrigger::AutoFraudDetection,
            );

            $newlySuspended[] = $businessId;
        }

        return $newlySuspended;
    }
}
