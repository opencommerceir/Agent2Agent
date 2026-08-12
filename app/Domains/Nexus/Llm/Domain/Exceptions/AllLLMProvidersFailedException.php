<?php

namespace App\Domains\Nexus\Llm\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by LLMRouter::route() when the primary provider and every entry
 * in the configured fallback chain have failed. docs/nexus-roadmap.md's
 * "use Rule Engine if all LLMs fail (never stop)" is satisfied by whatever
 * domain calls LLMRouter catching this and falling back to its own
 * existing deterministic logic (e.g. NegotiationReasoningService, already
 * unconditionally deterministic and cannot itself fail) — not by
 * LLMRouter inventing a second, generic Rule Engine that doesn't otherwise
 * exist in this codebase. No live caller catches this yet in Phase 4
 * (see docs/nexus/nexus_handoff.md Phase 4 decision on Negotiation
 * rewiring being deliberately out of scope); the contract is proven
 * correct by this phase's own tests and manual E2E instead.
 */
final class AllLLMProvidersFailedException extends RuntimeException
{
}
