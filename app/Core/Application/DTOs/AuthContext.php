<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\ValueObjects\Language;

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
 *
 * $language (Phase 4 Stage 4, i18n) is the request's already-detected
 * Language (query param -> Accept-Language header -> Tenant default ->
 * English — MCPGatewayController's own LanguageDetector call resolves this
 * once, before any handler runs). A handler that has nothing
 * language-specific to do simply ignores it, the same way Demo's handler
 * closures already ignore this DTO's other fields entirely (HANDOFF §1).
 */
final class AuthContext
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $agentId,
        public readonly Language $language = Language::English,
    ) {
    }

    public static function forAgent(AgentData $agent, Language $language = Language::English): self
    {
        return new self(
            tenantId: $agent->tenantId,
            agentId: $agent->id,
            language: $language,
        );
    }
}
