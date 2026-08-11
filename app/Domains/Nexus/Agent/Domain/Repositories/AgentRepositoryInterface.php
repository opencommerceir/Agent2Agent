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

    public function save(Agent $agent): Agent;
}
