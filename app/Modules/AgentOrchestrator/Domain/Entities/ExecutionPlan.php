<?php

namespace App\Modules\AgentOrchestrator\Domain\Entities;

/**
 * The ordered list of ExecutionSteps a Planner resolved for one Goal.
 * Immutable once built (a Planner produces a whole plan in one call,
 * there is no "add a step later" operation) — PlanExecutor only ever
 * reads it.
 */
final class ExecutionPlan
{
    /**
     * @param list<ExecutionStep> $steps
     */
    public function __construct(
        public readonly Goal $goal,
        public readonly array $steps,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->steps === [];
    }
}
