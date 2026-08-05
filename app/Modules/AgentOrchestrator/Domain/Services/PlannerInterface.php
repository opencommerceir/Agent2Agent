<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;

/**
 * Turns a Goal into an ordered ExecutionPlan of capability invocations,
 * guided by the calling Agent's own AgentProfile (§7.27) — its
 * `planning_rules`/`default_inputs` are what a Planner reads instead of
 * (Stage 1) hardcoding a goal-keyword-to-capability-list mapping in PHP.
 * Still deliberately takes no AuthContext, no tenant identity — a
 * Planner's job is purely "what capabilities, in what order, with what
 * input, would satisfy this goal for this Agent persona," a
 * tenant-independent decision. Which Agent is allowed to actually invoke
 * each planned capability is enforced later, per step, by
 * ToolInvokerInterface.
 *
 * `DeterministicPlanner` (Application/Services) is the only implementation
 * today — reads an AgentProfile's own config-declared rules, still no
 * real reasoning. A future LLM-based implementation is a drop-in
 * replacement behind this same Interface (Interfaces Over Tight
 * Coupling); nothing above the Interface — PlanExecutor, ExecuteGoalAction
 * — needs to change, and it would presumably still take the same
 * AgentProfile as context (a persona's own permissions/description are
 * exactly the kind of thing a real planner would want to reason with).
 */
interface PlannerInterface
{
    public function createPlan(Goal $goal, AgentProfile $profile): ExecutionPlan;
}
