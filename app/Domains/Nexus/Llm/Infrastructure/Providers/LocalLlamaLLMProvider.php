<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Providers;

use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use GuzzleHttp\ClientInterface;

/**
 * "Local Llama 3.2 8B" per docs/claude/llm-strategy.md's provider table —
 * "Lightweight and fast for intent/category detection", the documented
 * default `classification` provider. Same "no real local server in this
 * dev environment, configurable OpenAI-compatible endpoint, MockHandler in
 * tests" reasoning as `SelfHostedQwenLLMProvider`'s own docblock. Only
 * declares `Classification` support — it's the lightweight/fast model, not
 * meant for the heavier reasoning/negotiation workloads Qwen 14B handles.
 */
final class LocalLlamaLLMProvider extends AbstractOpenAiCompatibleProvider
{
    public function __construct(string $apiKey, string $model, string $baseUrl, ?ClientInterface $http = null)
    {
        parent::__construct('llama-3.2-3b-local', $apiKey, $model, $baseUrl, $http);
    }

    public function supports(LLMFeature $feature): bool
    {
        return $feature === LLMFeature::Classification;
    }

    protected function costFor(int $promptTokens, int $completionTokens): float
    {
        return 0.0;
    }
}
