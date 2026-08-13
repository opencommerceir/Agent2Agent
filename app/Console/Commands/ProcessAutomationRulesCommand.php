<?php

namespace App\Console\Commands;

use App\Domains\Nexus\Automation\Application\Actions\ProcessAutomationRulesAction;
use Illuminate\Console\Command;

/**
 * Scheduled hourly (routes/console.php) — the real periodic trigger for
 * Phase 8/M4's Automation Workflows engine, same "Schedule::command() +
 * withoutOverlapping()" shape DetectFraudSignalsCommand (Phase 6/M4)
 * already established (a lesson that phase's own docblock explicitly
 * flagged for reuse by later phases).
 */
class ProcessAutomationRulesCommand extends Command
{
    protected $signature = 'nexus:process-automation-rules';

    protected $description = 'Evaluate every Active AutomationRule and trigger the ones that are due';

    public function handle(ProcessAutomationRulesAction $processAutomationRules): int
    {
        $result = $processAutomationRules->execute();

        $this->info("{$result['triggered']} rule(s) triggered, {$result['failed']} failed.");

        return self::SUCCESS;
    }
}
