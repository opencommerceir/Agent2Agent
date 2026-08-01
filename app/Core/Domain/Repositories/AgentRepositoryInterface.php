<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\Agent;

/**
 * Contract owned by the Domain layer. Infrastructure provides the
 * implementation (Interfaces Over Tight Coupling).
 *
 * findById() intentionally does not take a tenant_id parameter: a primary
 * key lookup is not an authorization decision. Callers that need to enforce
 * "this agent belongs to this tenant" must compare Agent::tenantId()
 * themselves — baking that check into the repository would put an
 * authorization rule inside persistence code, which Repository Conventions
 * explicitly forbid.
 */
interface AgentRepositoryInterface
{
    public function findById(int $id): ?Agent;

    /**
     * Added for the Admin Dashboard's own Agents Management page (Phase 4
     * Stage 5) — the first thing that ever needed to list every Agent
     * platform-wide, the same reasoning TenantRepositoryInterface::all()
     * was added for the Tech Debt Sprint's scheduler (HANDOFF §7.13). The
     * Dashboard controller filters by tenant in PHP over this small,
     * admin-only list rather than adding a second, tenant-scoped listing
     * method.
     *
     * @return list<Agent>
     */
    public function all(): array;

    public function save(Agent $agent): Agent;
}
