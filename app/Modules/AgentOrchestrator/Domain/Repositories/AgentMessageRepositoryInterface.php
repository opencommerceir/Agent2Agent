<?php

namespace App\Modules\AgentOrchestrator\Domain\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentMessage;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;

/**
 * Persists `AgentMessage` communication log entries (Phase 6, Stage 5,
 * §7.30). Every method takes `tenantId` explicitly, never inferred from
 * ambient state (HANDOFF §3 pattern #1).
 */
interface AgentMessageRepositoryInterface
{
    public function save(AgentMessage $message): void;

    /**
     * Every message this persona sent or received, most recent first — not
     * filtered to `MessageStatus::Pending` alone (see `AgentMessage`'s own
     * docblock for why "pending" is a modeled-but-unreached state under
     * this stage's fully-synchronous implementation).
     *
     * @return list<AgentMessage>
     */
    public function findForAgent(int $tenantId, AgentType $agentType, int $limit): array;
}
