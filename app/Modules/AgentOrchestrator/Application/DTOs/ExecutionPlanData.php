<?php

namespace App\Modules\AgentOrchestrator\Application\DTOs;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;

/**
 * A read-only preview of an ExecutionPlan before (or instead of) it runs —
 * not returned by ExecuteGoalAction itself (which only ever returns the
 * post-execution ExecutionResultData), but the natural shape a future
 * "preview my plan before running it" capability would need (the same
 * "preview vs. durable apply" split HANDOFF §3 pattern #4 already
 * establishes elsewhere) — kept here for that reason even though nothing
 * in this MVP calls it yet.
 */
final class ExecutionPlanData
{
    /**
     * @param list<ExecutionStepData> $steps
     */
    public function __construct(
        public readonly GoalData $goal,
        public readonly array $steps,
    ) {
    }

    public static function fromEntity(ExecutionPlan $plan): self
    {
        return new self(
            goal: GoalData::fromEntity($plan->goal),
            steps: array_map(fn ($step) => ExecutionStepData::fromEntity($step), $plan->steps),
        );
    }

    /**
     * @return array{goal: array, steps: list<array>}
     */
    public function toArray(): array
    {
        return [
            'goal' => $this->goal->toArray(),
            'steps' => array_map(fn (ExecutionStepData $step) => $step->toArray(), $this->steps),
        ];
    }
}
