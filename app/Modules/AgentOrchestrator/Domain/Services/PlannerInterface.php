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
 * Two implementations exist as of Phase 6, Stage 3 (§7.28):
 * `DeterministicPlanner` (Application/Services, config-driven table
 * lookups, no real reasoning) and `LLMPlanner` (Application/Services,
 * delegates to a real LLM provider through `LLMClientInterface`, falling
 * back to a `DeterministicPlanner` instance on any failure). Which one is
 * bound to this Interface is a single config-driven choice
 * (`config('agent-orchestrator.planner.type')`,
 * `AgentOrchestratorServiceProvider::register()`) — nothing above the
 * Interface (PlanExecutor, ExecuteGoalAction, either HTTP/MCP surface)
 * needs to know or care which one is active (Interfaces Over Tight
 * Coupling).
 */
interface PlannerInterface
{
    public function createPlan(Goal $goal, AgentProfile $profile): ExecutionPlan;

    /**
     * A static capability descriptor, not a per-call runtime signal —
     * `false` for `DeterministicPlanner`, `true` for `LLMPlanner` (even
     * when `LLMPlanner` silently falls back to a deterministic plan on a
     * given call — see that class's own docblock for why per-call
     * "which planner actually produced this specific plan" tracking is a
     * real, honest gap, not built this stage, HANDOFF §8).
     */
    public function supportsLLM(): bool;
}
