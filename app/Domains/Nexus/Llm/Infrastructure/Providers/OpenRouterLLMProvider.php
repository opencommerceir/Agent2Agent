<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Providers;

use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use GuzzleHttp\ClientInterface;

/**
 * "OpenRouter Free Models" per docs/claude/llm-strategy.md's provider
 * table — the default model this provider is configured with
 * (config/nexus/platform.php's `llm.providers.openrouter.model`) is a
 * `:free`-suffixed one, so cost is always `0.0`. Supports every feature —
 * it's the platform's documented default `fallback` provider.
 */
final class OpenRouterLLMProvider extends AbstractOpenAiCompatibleProvider
{
    public function __construct(string $apiKey, string $model, string $baseUrl, ?ClientInterface $http = null)
    {
        parent::__construct('openrouter', $apiKey, $model, $baseUrl, $http, timeoutSeconds: 60);
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
