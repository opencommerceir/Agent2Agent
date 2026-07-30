<?php

namespace App\Core\Application\DTOs;

/**
 * The authenticated caller's identity, threaded explicitly into every
 * Capability handler (CapabilityHandlerRegistry's contract) instead of a
 * single tenantId scalar (Phase 2 decision, superseding the tenantId-only
 * widening). Cart ownership needed the calling Agent's own id, not just
 * its tenant — rather than growing the handler signature with another
 * positional int every time a new module needs a new piece of identity
 * (organizationId, etc. may come next), this groups everything the
 * authenticated Agent carries into one explicit, still-non-global
 * parameter (Explicit Over Magic: still passed as an argument, never
 * resolved from ambient/container state).
 */
final class AuthContext
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $agentId,
    ) {
    }

    public static function forAgent(AgentData $agent): self
    {
        return new self(
            tenantId: $agent->tenantId,
            agentId: $agent->id,
        );
    }
}
