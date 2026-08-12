<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Providers;

use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use GuzzleHttp\ClientInterface;

/**
 * Premium-only per docs/claude/llm-strategy.md's provider table ("Use only
 * for paid/high-value workflows") — supports every LLMFeature since it's
 * capable of all of them, admin routing decides when it's actually chosen.
 */
final class OpenAILLMProvider extends AbstractOpenAiCompatibleProvider
{
    /**
     * USD per 1K tokens — approximate GPT-4o pricing, monitored/updated by
     * an admin via config, not treated as a live price feed (same "seed
     * defaults only" caveat config/nexus/platform.php already uses
     * elsewhere).
     */
    private const PRICE_PER_1K_PROMPT_TOKENS = 0.0025;

    private const PRICE_PER_1K_COMPLETION_TOKENS = 0.01;

    public function __construct(string $apiKey, string $model, string $baseUrl, ?ClientInterface $http = null)
    {
        parent::__construct('openai', $apiKey, $model, $baseUrl, $http);
    }

    public function supports(LLMFeature $feature): bool
    {
        return true;
    }

    protected function costFor(int $promptTokens, int $completionTokens): float
    {
        return ($promptTokens / 1000 * self::PRICE_PER_1K_PROMPT_TOKENS)
            + ($completionTokens / 1000 * self::PRICE_PER_1K_COMPLETION_TOKENS);
    }
}
