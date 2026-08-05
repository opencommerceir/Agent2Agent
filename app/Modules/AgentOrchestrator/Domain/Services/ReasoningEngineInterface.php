<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;

/**
 * Produces a `ReasoningTrace` either before a Goal is planned (`think()`)
 * or after it has been executed (`reflect()`) — Phase 6, Stage 6 (§7.31),
 * the same "one Interface, a real LLM-backed implementation plus a
 * deterministic fallback the LLM one delegates to on failure" shape
 * `PlannerInterface`/`LLMPlanner`/`DeterministicPlanner` already establish
 * (§7.28). Takes plain `int $tenantId`, never `AuthContext` — this
 * Interface never invokes another capability, the same HANDOFF §3 pattern
 * #1 shape `LearningServiceInterface` already establishes.
 */
interface ReasoningEngineInterface
{
    public function think(Goal $goal, AgentProfile $profile, int $tenantId): ReasoningTrace;

    /**
     * `$executionId` is the real, already-persisted execution id
     * (`ExecuteGoalAction` only calls this after `ExecutionMemoryRepositoryInterface::save()`
     * has already run) — the returned trace is built with it already set,
     * unlike `$preReasoning`, which `think()` produced before that id
     * existed (see `ReasoningTrace`'s own docblock).
     */
    public function reflect(ExecutionResult $result, ReasoningTrace $preReasoning, int $tenantId, int $executionId): ReasoningTrace;
}
