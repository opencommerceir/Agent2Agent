<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Application\DTOs\AgentStrategyTemplateData;
use App\Domains\Nexus\Developer\Domain\Entities\AgentStrategyTemplate;
use App\Domains\Nexus\Developer\Domain\Repositories\AgentStrategyTemplateRepositoryInterface;

final class PublishAgentStrategyTemplateAction
{
    public function __construct(
        private readonly AgentStrategyTemplateRepositoryInterface $templates,
    ) {
    }

    public function execute(
        int $publisherBusinessId,
        string $nameFa,
        string $nameEn,
        string $descriptionFa,
        string $descriptionEn,
        ?string $personality,
        ?string $tone,
        array $strategies,
        int $priceCredits,
    ): AgentStrategyTemplateData {
        $template = AgentStrategyTemplate::publish(
            $publisherBusinessId, $nameFa, $nameEn, $descriptionFa, $descriptionEn, $personality, $tone, $strategies, $priceCredits,
        );

        return AgentStrategyTemplateData::fromEntity($this->templates->save($template));
    }
}
