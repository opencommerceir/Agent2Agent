<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Modules\AgentOrchestrator\Application\DTOs\AgentMessageData;
use App\Modules\AgentOrchestrator\Domain\Services\AgentCommunicationInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;

/**
 * Backs `agent.collaboration.messages` (Phase 6, Stage 5, §7.30). Plain
 * `int $tenantId`/`AgentType`, never `AuthContext` — this Action never
 * invokes another capability, the same HANDOFF §3 pattern #1 shape
 * `GetExecutionInsightsAction` (§7.29) already establishes.
 */
final class ListAgentMessagesAction
{
    private const DEFAULT_LIMIT = 20;

    public function __construct(
        private readonly AgentCommunicationInterface $communication,
    ) {
    }

    /**
     * @return list<AgentMessageData>
     */
    public function execute(int $tenantId, AgentType $agentType, ?int $limit = null): array
    {
        $messages = $this->communication->receive($tenantId, $agentType, $limit ?? self::DEFAULT_LIMIT);

        return array_map(fn ($message) => AgentMessageData::fromEntity($message), $messages);
    }
}
