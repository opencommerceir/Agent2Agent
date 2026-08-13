<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Domain\Repositories\AgentStrategyTemplateRepositoryInterface;
use InvalidArgumentException;

/**
 * The "dedicated sandbox" roadmap line, honestly scoped (Phase 9/M7): a
 * risk-free dry run showing exactly what a template WOULD change on an
 * Agent — never touches the database, never charges credits, never calls
 * InstallAgentStrategyTemplateAction. A parallel isolated tenant/Agent
 * pair was considered and rejected: it would need its own provisioning,
 * ownership, and cleanup lifecycle for a benefit this preview already
 * delivers (seeing the result before committing), without the risk this
 * action exists specifically to avoid — a template author accidentally
 * mutating their own *live*, already-negotiating Agent while testing.
 */
final class PreviewAgentStrategyTemplateAction
{
    public function __construct(
        private readonly AgentStrategyTemplateRepositoryInterface $templates,
    ) {
    }

    /**
     * @return array{personality: ?string, tone: ?string, strategies: array}
     */
    public function execute(int $templateId): array
    {
        $template = $this->templates->findById($templateId);

        if (! $template || $template->isRevoked()) {
            throw new InvalidArgumentException("AgentStrategyTemplate [{$templateId}] is not available.");
        }

        return [
            'personality' => $template->personality(),
            'tone' => $template->tone(),
            'strategies' => $template->strategies(),
        ];
    }
}
