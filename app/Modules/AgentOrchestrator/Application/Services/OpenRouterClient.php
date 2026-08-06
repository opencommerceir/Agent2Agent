<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Exceptions\LLMRequestFailedException;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * The real `LLMClientInterface` implementation for OpenRouter — a single
 * API in front of 100+ models (including several free ones, e.g.
 * `meta-llama/llama-3.1-405b-instruct:free`), OpenAI-compatible on its
 * Chat Completions endpoint, so this class's own request/response shape
 * mirrors `OpenAIClient` almost exactly (same "real client + injectable
 * `ClientInterface` for tests" shape `WooCommerceClient` established,
 * §7.6) with two genuine differences: `$baseUrl` is a real constructor
 * parameter, not hardcoded, since routing to a configurable endpoint is
 * this provider's whole reason to exist; and two extra, OpenRouter-specific
 * attribution headers (`HTTP-Referer`/`X-Title` — optional per OpenRouter's
 * own docs, sent anyway since they cost nothing and improve OpenRouter's
 * own per-app usage dashboards).
 *
 * No live OpenRouter credentials exist in this dev environment (same
 * "needs real credentials to test honestly" reasoning `OpenAIClient`/
 * `ClaudeClient`/every external Connector in this codebase gives) — every
 * test injects a fake `LLMClientInterface` or a Guzzle `MockHandler`-backed
 * real client instead.
 */
final class OpenRouterClient implements LLMClientInterface
{
    private const CHAT_COMPLETIONS_PATH = '/chat/completions';

    private const DEFAULT_MODEL = 'meta-llama/llama-3.1-405b-instruct:free';

    private const DEFAULT_BASE_URL = 'https://openrouter.ai/api/v1';

    private const ATTRIBUTION_REFERER = 'https://opencommerce.dev';

    private const ATTRIBUTION_TITLE = 'OpenCommerce Platform';

    private readonly ClientInterface $http;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
        ?ClientInterface $http = null,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
        ]);
    }

    public function complete(string $prompt, array $options = []): string
    {
        $body = $this->request([
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            ...$options,
        ]);

        return $this->extractMessageContent($body);
    }

    public function completeStructured(string $prompt, string $schema, array $options = []): array
    {
        $structuredPrompt = $prompt."\n\nRespond with ONLY a single JSON object matching this JSON Schema, no other text:\n{$schema}";

        $body = $this->request([
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $structuredPrompt]],
            'response_format' => ['type' => 'json_object'],
            ...$options,
        ]);

        return $this->decodeJson($this->extractMessageContent($body));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(array $payload): array
    {
        try {
            $response = $this->http->request('POST', self::CHAT_COMPLETIONS_PATH, [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => self::ATTRIBUTION_REFERER,
                    'X-Title' => self::ATTRIBUTION_TITLE,
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new LLMRequestFailedException("OpenRouter API request failed: {$e->getMessage()}", previous: $e);
        }

        return $this->decodeJson((string) $response->getBody());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function extractMessageContent(array $body): string
    {
        $content = $body['choices'][0]['message']['content'] ?? null;

        if (! is_string($content)) {
            throw new LLMRequestFailedException('OpenRouter API response did not contain a message content string.');
        }

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new LLMRequestFailedException('OpenRouter API returned a malformed (non-JSON-object) response.');
        }

        return $decoded;
    }
}
