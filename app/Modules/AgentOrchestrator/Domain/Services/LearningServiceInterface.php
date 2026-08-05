<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;

/**
 * Reads learned `ExecutionPattern`s back into a real `ExecutionPlan` a
 * Goal can skip planning for entirely (Phase 6, Stage 4, §7.29).
 *
 * Deliberately takes a real `Goal` (which already carries its own
 * `AgentType`, HANDOFF §7.26) rather than two raw strings the way the
 * original request's own pseudocode showed — the same "typed Domain
 * objects, not primitives, once inside the module" convention
 * `PlannerInterface::createPlan(Goal $goal, AgentProfile $profile)` already
 * establishes. `int $tenantId` is required and explicit (HANDOFF §3
 * pattern #1) even though `PlannerInterface` itself is deliberately
 * tenant-independent (see that Interface's own docblock) — a *learned*
 * suggestion is fundamentally tenant-scoped (one tenant's history must
 * never leak a suggestion into another's), so this is a different
 * contract with a different, narrower job: "has *this* tenant already
 * solved a goal like this," asked from `ExecuteGoalAction` (which already
 * legitimately holds `AuthContext`) before a Planner is even consulted —
 * see that Action's own docblock for exactly where this fits in the flow.
 */
interface LearningServiceInterface
{
    /**
     * `null` when no learned pattern matches closely enough to suggest
     * anything — the caller falls through to the configured
     * `PlannerInterface` exactly as if this method didn't exist.
     */
    public function suggestPlan(Goal $goal, int $tenantId): ?ExecutionPlan;

    /**
     * Aggregate stats over this tenant's own recent history for one Agent
     * persona — total/successful execution counts, average duration,
     * most-used capabilities, and success rate. See
     * `LearningService::getInsights()`'s own docblock for the bounded
     * window this reads over (not a full-table scan).
     *
     * @return array{total_executions: int, average_duration: float, most_used_capabilities: list<array{capability: string, count: int}>, success_rate: float, recent_goals: list<string>}
     */
    public function getInsights(int $tenantId, AgentType $agentType): array;
}
