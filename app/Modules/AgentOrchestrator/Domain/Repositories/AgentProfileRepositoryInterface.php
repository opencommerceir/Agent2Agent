<?php

namespace App\Modules\AgentOrchestrator\Domain\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;

/**
 * Reads Agent persona definitions from wherever they're configured —
 * today, `config/agents/{type}.php` (`ConfigBasedAgentProfileRepository`,
 * Infrastructure). Kept as a real Interface, not a direct
 * `ConfigBasedAgentProfileRepository` dependency, for the same
 * "Interfaces Over Tight Coupling" reason every other Repository in this
 * codebase is — a future implementation backed by a real `agent_profiles`
 * database table (letting an operator edit a profile without a
 * deployment) is a drop-in replacement behind this same contract.
 */
interface AgentProfileRepositoryInterface
{
    public function findByType(string $type): AgentProfile;

    /**
     * @return list<AgentProfile>
     */
    public function listAll(): array;
}
