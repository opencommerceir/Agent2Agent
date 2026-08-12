<?php

namespace App\Domains\Nexus\Llm\Domain\Exceptions;

use App\Core\Domain\Exceptions\Contracts\ConflictExceptionInterface;
use RuntimeException;

/**
 * Thrown by LLMBudgetGuard when a paid-provider candidate would exceed an
 * agent's daily or a business's monthly LLM budget (docs/claude/llm-strategy.md
 * §9/§10) — a legitimate business-rule rejection, never a malformed
 * request, same reasoning InsufficientCreditException already gives.
 * Implements ConflictExceptionInterface so MCPExceptionHandler maps it to
 * 409 CONFLICT with zero changes to Core. In normal operation LLMRouter
 * catches this per paid candidate and advances to the next (necessarily
 * free/local) candidate in the fallback chain rather than letting it
 * escape — "block paid providers, force local only" from the budget spec,
 * achieved without a special case.
 */
final class BudgetLimitExceededException extends RuntimeException implements ConflictExceptionInterface
{
}
