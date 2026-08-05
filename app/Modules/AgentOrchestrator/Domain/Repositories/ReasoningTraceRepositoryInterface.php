<?php

namespace App\Modules\AgentOrchestrator\Domain\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;

/**
 * Persists `ReasoningTrace`s (Phase 6, Stage 6, §7.31) — append-only, the
 * same shape `AgentMessageRepositoryInterface` (§7.30) already establishes
 * for a communication log entry that is never edited after it's written.
 *
 * `save()` requires `$trace->executionId() !== null` — a `PreExecution`
 * trace is built in memory before an execution id exists at all
 * (`ReasoningTrace`'s own docblock) and must have `assignExecutionId()`
 * called on it before it ever reaches this method; there is deliberately
 * no "save now, patch execution_id later" path, since every real caller
 * (`ExecuteGoalAction`) always knows the real id by the time it persists
 * anything.
 */
interface ReasoningTraceRepositoryInterface
{
    public function save(ReasoningTrace $trace): void;

    /**
     * Both traces for one finished execution, oldest first (`PreExecution`
     * before `PostExecution` under normal completion) — either or both may
     * be absent (an execution that failed before `reflect()` ever ran
     * leaves only a `PreExecution` trace behind, an honest, documented gap,
     * not a bug; see `docs/self-reflection.md`).
     *
     * @return list<ReasoningTrace>
     */
    public function findByExecution(int $tenantId, int $executionId): array;
}
