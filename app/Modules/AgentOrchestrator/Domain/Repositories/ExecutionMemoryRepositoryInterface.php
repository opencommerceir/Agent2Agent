<?php

namespace App\Modules\AgentOrchestrator\Domain\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;

/**
 * Persists a Goal's finished ExecutionResult (and its child
 * ExecutionSteps, owned here the same "repo owns its child records" shape
 * WorkflowRepositoryInterface/InvoiceRepositoryInterface already
 * establish — HANDOFF §3). Every method takes tenantId explicitly, never
 * inferred from ambient state (the same convention every Repository
 * Interface in this codebase follows).
 *
 * "Memory" names this module's own historical record of what it has
 * already done — a simple relational log today, not the semantic/
 * vector-search long-term memory a future LLM-based planner may want
 * (see docs/agent-orchestrator.md's own roadmap section); the name is
 * chosen to already fit that future without implying it exists yet.
 */
interface ExecutionMemoryRepositoryInterface
{
    /**
     * @return array{id: int, result: ExecutionResult}
     */
    public function save(ExecutionResult $result, int $tenantId, int $agentId, AgentType $agentType): array;

    /**
     * `createdAt` (Showcase prep, Phase 3, §7.33) is a real, additive
     * widening — an ISO-8601 string of the underlying record's own
     * timestamp, not new data this Interface didn't already have access
     * to (HANDOFF §3 pattern #6).
     *
     * @return ?array{id: int, tenantId: int, agentId: int, agentType: AgentType, result: ExecutionResult, createdAt: ?string}
     */
    public function findById(int $id, int $tenantId): ?array;

    /**
     * @return list<array{id: int, tenantId: int, agentId: int, agentType: AgentType, result: ExecutionResult, createdAt: ?string}>
     */
    public function list(int $tenantId, ?AgentType $agentType, ?string $status, int $limit): array;
}
