<?php

namespace App\Domains\Nexus\Agent\Domain\Repositories;

use App\Domains\Nexus\Agent\Domain\Entities\Agent;

/**
 * Contract owned by the Domain layer. Infrastructure provides the
 * implementation (Interfaces Over Tight Coupling).
 */
interface AgentRepositoryInterface
{
    public function findById(int $id): ?Agent;

    public function findByBusinessId(int $businessId): ?Agent;

    /**
     * Resolves "which Nexus Agent is this" from the Core Agent id an MCP
     * Bearer token authenticates as (AuthContext::agentId) — the payoff of
     * CreateAgentForBusinessAction provisioning a real Core Agent per
     * Nexus Agent (docs/nexus/nexus_handoff.md, Phase 1/M3).
     */
    public function findByCoreAgentId(int $coreAgentId): ?Agent;

    public function save(Agent $agent): Agent;
}
