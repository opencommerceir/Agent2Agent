<?php

namespace App\Domains\Nexus\Llm\Domain\Exceptions;

use RuntimeException;

/**
 * Normalizes every provider-specific failure (network error, non-2xx
 * response, malformed JSON body) — thrown by every
 * Infrastructure\Providers\*LLMProvider, the same "one exception type per
 * external Connector" idiom OpenAIClient/ClaudeClient/ZibalPaymentGateway
 * already use. Implements neither Core marker interface: a broken external
 * LLM dependency is neither "not found" nor "a business-rule conflict" —
 * falls to MCPExceptionHandler's default 500 branch if it ever escapes
 * LLMRouter unhandled (in normal operation LLMRouter catches this per
 * candidate and tries the next one in the fallback chain).
 */
final class LLMProviderRequestFailedException extends RuntimeException
{
}
