<?php

namespace App\Modules\AgentOrchestrator\Domain\Events;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;

/**
 * Domain event: dispatched by PlanExecutor at each of a step's own
 * lifecycle transitions. `phase` is one of 'started'/'completed'/'failed'
 * — a plain string rather than reusing StepStatus, since 'started' has no
 * corresponding StepStatus case (a running step's status is already
 * StepStatus::Running by the time this fires; 'started' names the
 * transition, not the resulting state). `durationMs` is null on 'started'
 * (nothing has elapsed yet) and populated on 'completed'/'failed'.
 */
final class StepExecuted
{
    public function __construct(
        public readonly ExecutionStep $step,
        public readonly string $phase,
        public readonly ?int $durationMs = null,
    ) {
    }
}
