<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Modules\AgentOrchestrator\Application\DTOs\ExecutionPlanData;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Services\LearningServiceInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;

/**
 * Backs `agent.memory.suggest` (Phase 6, Stage 4, §7.29) — a read-only
 * preview of what `ExecuteGoalAction` *would* use as a learned plan for
 * this goal, without actually running it. The first real caller of
 * `ExecutionPlanData` (Stage 1, §7.26, built for exactly this "preview my
 * plan" shape but unused until now — see that DTO's own docblock).
 */
final class SuggestExecutionPlanAction
{
    public function __construct(
        private readonly LearningServiceInterface $learning,
    ) {
    }

    public function execute(string $goalText, AgentType $agentType, int $tenantId): ?ExecutionPlanData
    {
        $goal = Goal::fromText($goalText, $agentType);
        $plan = $this->learning->suggestPlan($goal, $tenantId);

        return $plan !== null ? ExecutionPlanData::fromEntity($plan) : null;
    }
}
