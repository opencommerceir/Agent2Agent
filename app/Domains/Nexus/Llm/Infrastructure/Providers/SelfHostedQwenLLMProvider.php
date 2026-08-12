<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Providers;

use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use GuzzleHttp\ClientInterface;

/**
 * "Local Qwen 2.5 14B" per docs/claude/llm-strategy.md's provider table —
 * the documented default `reasoning`/`negotiation` provider ("Best
 * private/default option for reasoning tasks"). No real local model server
 * runs in this dev environment (same honest limitation every other
 * external Connector in this codebase already documents) — `$baseUrl`
 * points at a configurable OpenAI-compatible endpoint
 * (config/nexus/platform.php's `llm.providers.qwen-14b-local.base_url`,
 * defaulting to an Ollama-style `http://localhost:11434/v1`); every test
 * injects a Guzzle `MockHandler`-backed client with canned Ollama-shaped
 * JSON fixtures, never a live local server. Inference on owned
 * infrastructure is treated as costing the platform nothing marginal, so
 * `estimateCost()`/`costFor()` are unconditionally `0.0` — never a
 * token-based calculation.
 */
final class SelfHostedQwenLLMProvider extends AbstractOpenAiCompatibleProvider
{
    public function __construct(string $apiKey, string $model, string $baseUrl, ?ClientInterface $http = null)
    {
        parent::__construct('qwen-14b-local', $apiKey, $model, $baseUrl, $http);
    }

    public function supports(LLMFeature $feature): bool
    {
        return in_array($feature, [LLMFeature::Reasoning, LLMFeature::Negotiation, LLMFeature::Classification], true);
    }

    protected function costFor(int $promptTokens, int $completionTokens): float
    {
        return 0.0;
    }
}
