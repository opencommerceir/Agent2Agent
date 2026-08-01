<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\AgentData;
use App\Core\Domain\Exceptions\AgentNotFoundException;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\ValueObjects\AgentType;

/**
 * Not named in Phase 4 Stage 5's own request, but its "Agents Management
 * ... Create, Edit" Dashboard page implies it — no Action existed to
 * rename an Agent or change its type before this. tenant_id/organization_id
 * are deliberately not editable here, the same "identity fields fixed at
 * registration" shape Tenant's own slug/Product's own SKU have.
 */
final class UpdateAgentAction
{
    public function __construct(
        private readonly AgentRepositoryInterface $agents,
    ) {
    }

    public function execute(int $id, string $name, string $type): AgentData
    {
        $agent = $this->agents->findById($id);

        if (! $agent) {
            throw new AgentNotFoundException("Agent [{$id}] does not exist.");
        }

        $agent->rename($name);
        $agent->changeType(AgentType::from($type));

        $agent = $this->agents->save($agent);

        return AgentData::fromEntity($agent);
    }
}
