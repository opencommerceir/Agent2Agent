<?php

namespace App\Modules\AgentOrchestrator\Domain\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPattern;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;

/**
 * Persists learned `ExecutionPattern`s (Phase 6, Stage 4, §7.29). Every
 * method takes `tenantId` explicitly, never inferred from ambient state —
 * the same convention every Repository Interface in this codebase follows
 * (HANDOFF §3 pattern #1) — since a pattern learned from one tenant's own
 * execution history must never leak a suggestion into another tenant's.
 */
interface ExecutionPatternRepositoryInterface
{
    /**
     * Insert a brand-new pattern (`$pattern->id() === null`, the repository
     * assigns one via `$pattern->assignId()`) or persist an already-loaded
     * one's updated `usageCount`/`successRate`/capabilities back to its own
     * row — never inserts a second row for the same (tenantId, goalPattern,
     * agentType), see `findExisting()`.
     */
    public function save(ExecutionPattern $pattern): void;

    /**
     * The exact-match lookup the write side uses before deciding whether a
     * new occurrence should update an existing pattern or `create()` a new
     * one — a composite-key equivalent of `findById()`, since a pattern's
     * real identity here is (tenantId, goalPattern, agentType), not a
     * caller-supplied numeric id.
     */
    public function findExisting(int $tenantId, string $goalPattern, AgentType $agentType): ?ExecutionPattern;

    /**
     * Every pattern for this tenant/Agent persona whose own `matches($goal)`
     * returns true, most-successful first (`success_rate` desc, then
     * `usage_count` desc as a tiebreaker — a pattern tried twice at 100% is
     * a stronger signal than one tried once) — the read side
     * `LearningService::suggestPlan()` picks its best match from.
     *
     * @return list<ExecutionPattern>
     */
    public function findSimilarPatterns(int $tenantId, string $goal, AgentType $agentType, int $limit): array;
}
