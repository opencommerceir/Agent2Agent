<?php

namespace App\Console\Commands;

use App\Domains\Nexus\Reputation\Application\Actions\DetectFraudSignalsAction;
use Illuminate\Console\Command;

/**
 * Scheduled hourly (routes/console.php) — the real periodic trigger for
 * Phase 6/M4's rule-based fraud detection, same "Schedule::command() +
 * withoutOverlapping()" shape every other Nexus/Commerce background job
 * in this codebase already uses. The admin Fraud queue's "Run detection
 * now" button (NexusFraudController::runDetection()) calls the same
 * Action directly for an on-demand check between scheduled runs.
 */
class DetectFraudSignalsCommand extends Command
{
    protected $signature = 'nexus:detect-fraud';

    protected $description = 'Auto-suspend Businesses that cross the dispute-loss fraud threshold';

    public function handle(DetectFraudSignalsAction $detectFraudSignals): int
    {
        $suspended = $detectFraudSignals->execute();

        $this->info(count($suspended).' business(es) suspended.');

        return self::SUCCESS;
    }
}
