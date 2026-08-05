<?php

namespace App\Modules\AgentOrchestrator\Domain\Exceptions;

use RuntimeException;

/**
 * Normalizes every LLM-provider-specific failure (network error, non-2xx
 * response, malformed/unparseable JSON body) — thrown by
 * `OpenAIClient`/`ClaudeClient`, the same shape `WooCommerceApiException`
 * already establishes for Commerce's own external API client (§7.6).
 * Implements neither Core marker interface: a broken external LLM
 * dependency is neither "not found" nor "a business-rule conflict."
 *
 * Always caught by `LLMPlanner` (never expected to reach an HTTP caller
 * directly) and turned into a fallback to the deterministic planner —
 * unless `config('agent-orchestrator.planner.fallback_to_deterministic')`
 * is `false`, in which case it propagates and `MCPExceptionHandler`'s own
 * default branch maps it to `INTERNAL_ERROR`/500, the correct status for
 * a genuinely broken external dependency.
 */
final class LLMRequestFailedException extends RuntimeException
{
}
