<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Modules\AgentOrchestrator\Application\DTOs\AgentProfileData;
use App\Modules\AgentOrchestrator\Domain\Repositories\AgentProfileRepositoryInterface;

final class GetAgentProfileAction
{
    public function __construct(
        private readonly AgentProfileRepositoryInterface $profiles,
    ) {
    }

    public function execute(string $agentType): AgentProfileData
    {
        return AgentProfileData::fromEntity($this->profiles->findByType($agentType));
    }
}
