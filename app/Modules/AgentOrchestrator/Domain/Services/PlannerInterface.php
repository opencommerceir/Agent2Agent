<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;

/**
 * Turns a Goal into an ordered ExecutionPlan of capability invocations.
 * Deliberately takes only a Goal — no AuthContext, no tenant/agent
 * identity — a Planner's job is purely "what capabilities, in what order,
 * with what input, would satisfy this goal," a tenant-independent
 * decision. Which Agent is allowed to actually invoke each planned
 * capability is enforced later, per step, by ToolInvokerInterface.
 *
 * `DeterministicPlanner` (Application/Services) is the only implementation
 * today — hardcoded keyword rules, the MVP named in this module's own
 * request. A future LLM-based implementation is a drop-in replacement
 * behind this same Interface (Interfaces Over Tight Coupling); nothing
 * above the Interface — PlanExecutor, ExecuteGoalAction — needs to change.
 */
interface PlannerInterface
{
    public function createPlan(Goal $goal): ExecutionPlan;
}
