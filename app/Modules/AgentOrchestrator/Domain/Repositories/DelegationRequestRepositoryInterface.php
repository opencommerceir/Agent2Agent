<?php

namespace App\Modules\AgentOrchestrator\Domain\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\DelegationRequest;

/**
 * Persists `DelegationRequest`s (Phase 6, Stage 5, §7.30). Every method
 * takes `tenantId` explicitly (HANDOFF §3 pattern #1).
 */
interface DelegationRequestRepositoryInterface
{
    /**
     * A single upsert by the Entity's own `id()` — `null` inserts a new
     * row and assigns the real id back onto the given Entity via
     * `DelegationRequest::assignId()`, the same shape
     * `EloquentExecutionPatternRepository::save()` already establishes
     * (§7.29). `AgentCommunicationService::requestDelegation()` calls this
     * exactly once, with the request already in its final terminal state
     * — see that method's own docblock for why no intermediate
     * `Pending`/`InProgress` row is ever separately persisted this stage.
     */
    public function save(DelegationRequest $request): void;

    public function findById(int $id, int $tenantId): ?DelegationRequest;
}
