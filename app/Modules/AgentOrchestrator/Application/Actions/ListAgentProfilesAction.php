<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Modules\AgentOrchestrator\Application\DTOs\AgentProfileData;
use App\Modules\AgentOrchestrator\Domain\Repositories\AgentProfileRepositoryInterface;

final class ListAgentProfilesAction
{
    public function __construct(
        private readonly AgentProfileRepositoryInterface $profiles,
    ) {
    }

    /**
     * @return list<AgentProfileData>
     */
    public function execute(): array
    {
        return array_map(
            fn ($profile) => AgentProfileData::fromEntity($profile),
            $this->profiles->listAll(),
        );
    }
}
