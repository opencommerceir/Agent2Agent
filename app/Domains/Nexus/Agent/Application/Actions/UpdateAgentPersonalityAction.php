<?php

namespace App\Domains\Nexus\Agent\Application\Actions;

use App\Domains\Nexus\Agent\Application\DTOs\AgentData;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use InvalidArgumentException;

final class UpdateAgentPersonalityAction
{
    public function __construct(
        private readonly AgentRepositoryInterface $agents,
    ) {
    }

    public function execute(int $agentId, string $personality, string $tone): AgentData
    {
        $agent = $this->agents->findById($agentId);

        if (! $agent) {
            throw new InvalidArgumentException("Agent [{$agentId}] does not exist.");
        }

        $agent->updatePersonality($personality, $tone);
        $agent = $this->agents->save($agent);

        return AgentData::fromEntity($agent);
    }
}
