<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Providers;

use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use GuzzleHttp\ClientInterface;

/**
 * "Groq Free Tier" per docs/claude/llm-strategy.md's provider table —
 * `~500 tok/s`, free, useful when low latency matters. Groq's own API is
 * OpenAI-Chat-Completions-compatible. Supports every feature.
 */
final class GroqLLMProvider extends AbstractOpenAiCompatibleProvider
{
    public function __construct(string $apiKey, string $model, string $baseUrl, ?ClientInterface $http = null)
    {
        parent::__construct('groq', $apiKey, $model, $baseUrl, $http);
    }

    public function supports(LLMFeature $feature): bool
    {
        return true;
    }

    protected function costFor(int $promptTokens, int $completionTokens): float
    {
        return 0.0;
    }
}
