<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Application\DTOs\AgentStrategyTemplateData;
use App\Domains\Nexus\Developer\Domain\Repositories\AgentStrategyTemplateRepositoryInterface;

final class ListMyPublishedTemplatesAction
{
    public function __construct(
        private readonly AgentStrategyTemplateRepositoryInterface $templates,
    ) {
    }

    /**
     * @return list<AgentStrategyTemplateData>
     */
    public function execute(int $publisherBusinessId): array
    {
        return array_values(array_map(
            fn ($template) => AgentStrategyTemplateData::fromEntity($template),
            $this->templates->findByPublisherBusinessId($publisherBusinessId),
        ));
    }
}
