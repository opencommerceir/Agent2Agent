<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\AgentData;
use App\Core\Domain\Exceptions\AgentNotFoundException;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\ValueObjects\AgentStatus;

/**
 * Backs the Dashboard's own Suspend/Activate buttons — a thin wrapper
 * around Agent's own activate()/suspend() mutators (already existed since
 * Phase 1) so the Dashboard controller never touches the Repository or
 * the Entity's mutators directly (Dashboard Controllers Rule: no business
 * logic in Controllers).
 */
final class ChangeAgentStatusAction
{
    public function __construct(
        private readonly AgentRepositoryInterface $agents,
    ) {
    }

    public function execute(int $id, AgentStatus $status): AgentData
    {
        $agent = $this->agents->findById($id);

        if (! $agent) {
            throw new AgentNotFoundException("Agent [{$id}] does not exist.");
        }

        match ($status) {
            AgentStatus::Active => $agent->activate(),
            AgentStatus::Inactive => $agent->deactivate(),
            AgentStatus::Suspended => $agent->suspend(),
        };

        $agent = $this->agents->save($agent);

        return AgentData::fromEntity($agent);
    }
}
