<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Exceptions\LLMRequestFailedException;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * The real LLMClientInterface implementation for OpenAI's own Chat
 * Completions API — the same "real client + injectable ClientInterface
 * for tests" shape `WooCommerceClient` already establishes (§7.6): no
 * live OpenAI credentials exist in this dev environment (same
 * "needs real credentials to test honestly" reasoning every external
 * Connector in this codebase gives), so every test injects its own
 * Guzzle `MockHandler`-backed client via the constructor's optional
 * `$http` parameter rather than hitting the real API.
 *
 * `completeStructured()` uses `response_format: {type: 'json_object'}`
 * (broadly supported across current GPT-4-family models) plus the
 * schema embedded directly in the prompt text — OpenAI's Chat
 * Completions API has no separate "pass a JSON Schema, get back exactly
 * that shape" parameter the way this Interface's own `$schema` argument
 * might suggest; `json_object` mode only guarantees syntactically valid
 * JSON, not schema conformance. `LLMPlanner`'s own prompt is written to
 * ask for the schema's shape explicitly for this reason.
 */
final class OpenAIClient implements LLMClientInterface
{
    private const CHAT_COMPLETIONS_PATH = '/v1/chat/completions';

    private readonly ClientInterface $http;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4',
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => 'https://api.openai.com',
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
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new LLMRequestFailedException("OpenAI API request failed: {$e->getMessage()}", previous: $e);
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
            throw new LLMRequestFailedException('OpenAI API response did not contain a message content string.');
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
            throw new LLMRequestFailedException('OpenAI API returned a malformed (non-JSON-object) response.');
        }

        return $decoded;
    }
}
