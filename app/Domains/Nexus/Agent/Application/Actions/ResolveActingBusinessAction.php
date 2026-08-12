<?php

namespace App\Domains\Nexus\Agent\Application\Actions;

use App\Core\Domain\Exceptions\PermissionDeniedException;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use InvalidArgumentException;

/**
 * Shared by every Nexus MCP capability handler that needs to know "which
 * Business is actually calling" — AuthContext only carries the Core
 * Agent's identity (tenantId/agentId), never a Nexus concept directly.
 * Kept as one Action rather than duplicated inline in each
 * ServiceProvider::boot() closure (Marketplace's M2, Negotiation's M4).
 *
 * Phase 6/M4 — also the single choke point that blocks a Suspended
 * Business's Agent from EVERY Nexus MCP capability at once (literally
 * every handler resolves the calling Business through here), rather than
 * repeating an isActive() check in each Action individually. Reuses
 * Core's own PermissionDeniedException (MCPExceptionHandler already maps
 * it to 403 FORBIDDEN) instead of inventing a new one — "Extend, Don't
 * Rebuild" for exceptions too.
 */
final class ResolveActingBusinessAction
{
    public function __construct(
        private readonly AgentRepositoryInterface $agents,
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    public function execute(int $coreAgentId): int
    {
        $agent = $this->agents->findByCoreAgentId($coreAgentId);

        if (! $agent) {
            throw new InvalidArgumentException("No Nexus Agent is linked to Core Agent [{$coreAgentId}].");
        }

        $business = $this->businesses->findById($agent->businessId());

        if ($business && ! $business->isActive()) {
            throw new PermissionDeniedException("Business [{$agent->businessId()}] is suspended.");
        }

        return $agent->businessId();
    }
}
