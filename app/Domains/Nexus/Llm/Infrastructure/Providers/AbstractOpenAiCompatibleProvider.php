<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Providers;

use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\Services\LLMProviderInterface;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMResponse;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Shared HTTP logic for every provider that speaks the OpenAI Chat
 * Completions wire format — OpenAI itself, OpenRouter, Groq, and the two
 * self-hosted/local providers (Ollama/vLLM/LM Studio/llama.cpp's server all
 * implement this exact shape too). Only AnthropicLLMProvider is a genuinely
 * separate implementation (Messages API, different headers, different
 * response/usage shape — mirrors how ClaudeClient already differs from
 * OpenAIClient in AgentOrchestrator).
 *
 * `$baseUrl` is expected to already include any API-version path segment
 * (e.g. `https://openrouter.ai/api/v1`, `http://localhost:11434/v1`) — the
 * constructor's own `rtrim($baseUrl, '/').'/'` plus this class's relative
 * (no leading slash) request path is the standard Guzzle fix for a real,
 * previously-hit bug in this codebase: per RFC 3986 §5.3, a request path
 * starting with `/` is an absolute-path reference that *replaces*
 * `base_uri`'s own path entirely rather than appending to it, so
 * `base_uri` `https://openrouter.ai/api/v1` + request path
 * `/chat/completions` would silently resolve to
 * `https://openrouter.ai/chat/completions` (see
 * `App\Modules\AgentOrchestrator\Application\Services\OpenRouterClient`'s
 * own docblock for the incident this guards against).
 */
abstract class AbstractOpenAiCompatibleProvider implements LLMProviderInterface
{
    private const CHAT_COMPLETIONS_PATH = 'chat/completions';

    private readonly ClientInterface $http;

    public function __construct(
        private readonly string $name,
        private readonly string $apiKey,
        private readonly string $model,
        string $baseUrl,
        ?ClientInterface $http = null,
        int $timeoutSeconds = 30,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => rtrim($baseUrl, '/').'/',
            'timeout' => $timeoutSeconds,
        ]);
    }

    public function chat(array $messages, array $options = []): LLMResponse
    {
        $startedAt = microtime(true);

        try {
            $response = $this->http->request('POST', self::CHAT_COMPLETIONS_PATH, [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => $messages,
                    ...$options,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new LLMProviderRequestFailedException("{$this->name} API request failed: {$e->getMessage()}", previous: $e);
        }

        $latencyMs = (microtime(true) - $startedAt) * 1000;
        $body = $this->decodeJson((string) $response->getBody());

        $content = $body['choices'][0]['message']['content'] ?? null;

        if (! is_string($content)) {
            throw new LLMProviderRequestFailedException("{$this->name} API response did not contain a message content string.");
        }

        $promptTokens = (int) ($body['usage']['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($body['usage']['completion_tokens'] ?? 0);

        return LLMResponse::success(
            content: $content,
            provider: $this->name,
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

        // Rough, conservative token estimate (~4 chars/token) used only to
        // pre-check a paid provider's cost before a real call — the real,
        // billed figure always comes from the provider's own `usage` block
        // in `chat()`'s response.
        $approxPromptTokens = (int) ceil($characterCount / 4);

        return $this->costFor($approxPromptTokens, 0);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Per-call USD cost for the given token counts — `0.0` for every free/
     * local provider (their PRICING table is simply empty), a real
     * per-1K-token calculation for paid ones. The single place each
     * subclass's own price list is applied.
     */
    abstract protected function costFor(int $promptTokens, int $completionTokens): float;

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new LLMProviderRequestFailedException("{$this->name} API returned a malformed (non-JSON-object) response.");
        }

        return $decoded;
    }
}
