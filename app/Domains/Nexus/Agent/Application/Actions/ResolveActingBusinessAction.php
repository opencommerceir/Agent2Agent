<?php

namespace App\Domains\Nexus\Agent\Application\Actions;

use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use InvalidArgumentException;

/**
 * Shared by every Nexus MCP capability handler that needs to know "which
 * Business is actually calling" — AuthContext only carries the Core
 * Agent's identity (tenantId/agentId), never a Nexus concept directly.
 * Kept as one Action rather than duplicated inline in each
 * ServiceProvider::boot() closure (Marketplace's M2, Negotiation's M4).
 */
final class ResolveActingBusinessAction
{
    public function __construct(
        private readonly AgentRepositoryInterface $agents,
    ) {
    }

    public function execute(int $coreAgentId): int
    {
        $agent = $this->agents->findByCoreAgentId($coreAgentId);

        if (! $agent) {
            throw new InvalidArgumentException("No Nexus Agent is linked to Core Agent [{$coreAgentId}].");
        }

        return $agent->businessId();
    }
}
