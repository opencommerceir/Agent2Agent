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

    public function save(Agent $agent): Agent;
}
