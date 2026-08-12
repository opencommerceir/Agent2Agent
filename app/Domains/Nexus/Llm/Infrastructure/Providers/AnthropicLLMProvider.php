<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Providers;

use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\Services\LLMProviderInterface;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMResponse;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * "Anthropic Claude" per docs/claude/llm-strategy.md's provider table —
 * premium-only, supports every feature. Deliberately NOT a subclass of
 * AbstractOpenAiCompatibleProvider: Anthropic's Messages API differs
 * enough (path, `x-api-key`/`anthropic-version` headers instead of Bearer
 * auth, `max_tokens` required in the request body, `usage.input_tokens`/
 * `output_tokens` instead of `usage.prompt_tokens`/`completion_tokens`,
 * response text under `content[0].text` instead of
 * `choices[0].message.content`) that forcing it into the shared base would
 * be the wrong abstraction — the same reasoning
 * `App\Modules\AgentOrchestrator\Application\Services\ClaudeClient`
 * already documents relative to `OpenAIClient`.
 */
final class AnthropicLLMProvider implements LLMProviderInterface
{
    private const MESSAGES_PATH = 'v1/messages';

    private const API_VERSION = '2023-06-01';

    private const DEFAULT_MAX_TOKENS = 4096;

    /**
     * USD per 1K tokens — approximate Claude 3 Opus pricing, seed default
     * only, same caveat as OpenAILLMProvider's own price constants.
     */
    private const PRICE_PER_1K_PROMPT_TOKENS = 0.015;

    private const PRICE_PER_1K_COMPLETION_TOKENS = 0.075;

    private readonly ClientInterface $http;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        string $baseUrl,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => rtrim($baseUrl, '/').'/',
            'timeout' => 30,
        ]);
    }

    public function chat(array $messages, array $options = []): LLMResponse
    {
        $startedAt = microtime(true);

        try {
            $response = $this->http->request('POST', self::MESSAGES_PATH, [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => self::DEFAULT_MAX_TOKENS,
                    'messages' => $messages,
                    ...$options,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new LLMProviderRequestFailedException("Anthropic API request failed: {$e->getMessage()}", previous: $e);
        }

        $latencyMs = (microtime(true) - $startedAt) * 1000;
        $body = $this->decodeJson((string) $response->getBody());

        $text = $body['content'][0]['text'] ?? null;

        if (! is_string($text)) {
            throw new LLMProviderRequestFailedException('Anthropic API response did not contain a text content block.');
        }

        $promptTokens = (int) ($body['usage']['input_tokens'] ?? 0);
        $completionTokens = (int) ($body['usage']['output_tokens'] ?? 0);

        return LLMResponse::success(
            content: $text,
            provider: 'claude',
            model: is_string($body['model'] ?? null) ? $body['model'] : $this->model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            estimatedCost: $this->costFor($promptTokens, $completionTokens),
            latencyMs: $latencyMs,
        );
    }

    public function estimateCost(array $messages): float
    {
        $characterCount = array_sum(array_map(
            static fn (array $message) => strlen((string) ($message['content'] ?? '')),
            $messages,
        ));

        return $this->costFor((int) ceil($characterCount / 4), 0);
    }

    public function supports(LLMFeature $feature): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'claude';
    }

    private function costFor(int $promptTokens, int $completionTokens): float
    {
        return ($promptTokens / 1000 * self::PRICE_PER_1K_PROMPT_TOKENS)
            + ($completionTokens / 1000 * self::PRICE_PER_1K_COMPLETION_TOKENS);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new LLMProviderRequestFailedException('Anthropic API returned a malformed (non-JSON-object) response.');
        }

        return $decoded;
    }
}
