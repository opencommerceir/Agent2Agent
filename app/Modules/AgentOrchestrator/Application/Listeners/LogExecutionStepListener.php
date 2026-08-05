<?php

namespace App\Modules\AgentOrchestrator\Application\Listeners;

use App\Modules\AgentOrchestrator\Domain\Events\StepExecuted;
use Illuminate\Support\Facades\Log;

/**
 * Owns every "a step ran" log line this module's own request asked for
 * ("هر مرحله لاگ شود") — kept out of PlanExecutor itself so that class's
 * only job stays "run the plan," matching this codebase's own convention
 * of a Listener reacting to a Domain Event rather than the dispatching
 * class doing the reacting inline (the same shape `InventoryLowListener`
 * reacting to `InventoryWasCommitted` already establishes, HANDOFF §7.9).
 */
final class LogExecutionStepListener
{
    public function handle(StepExecuted $event): void
    {
        match ($event->phase) {
            'started' => Log::info('Execution started', [
                'step' => $event->step->capability,
            ]),
            'completed' => Log::info('Execution finished', [
                'step' => $event->step->capability,
                'duration_ms' => $event->durationMs,
            ]),
            'failed' => Log::error('Execution failed', [
                'step' => $event->step->capability,
                'error' => $event->step->errorMessage(),
            ]),
            default => null,
        };
    }
}
