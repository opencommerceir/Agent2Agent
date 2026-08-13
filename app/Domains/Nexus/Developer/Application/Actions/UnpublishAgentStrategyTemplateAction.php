<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Domain\Repositories\AgentStrategyTemplateRepositoryInterface;
use InvalidArgumentException;

final class UnpublishAgentStrategyTemplateAction
{
    public function __construct(
        private readonly AgentStrategyTemplateRepositoryInterface $templates,
    ) {
    }

    public function execute(int $templateId, int $actingBusinessId): void
    {
        $template = $this->templates->findById($templateId);

        if (! $template || $template->publisherBusinessId() !== $actingBusinessId) {
            throw new InvalidArgumentException("AgentStrategyTemplate [{$templateId}] does not belong to Business [{$actingBusinessId}].");
        }

        $template->revoke();

        $this->templates->save($template);
    }
}
