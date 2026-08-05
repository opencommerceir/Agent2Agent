<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Exceptions\LLMRequestFailedException;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * The real LLMClientInterface implementation for Anthropic's own Messages
 * API — see `OpenAIClient`'s own docblock for why every test injects a
 * fake `ClientInterface` instead of hitting the real API.
 *
 * `completeStructured()` uses Anthropic's own tool-use mechanism (a
 * single forced tool call, `tool_choice: {type: 'tool', name: ...}`,
 * `input_schema` set to the caller's own `$schema`) rather than prompting
 * for JSON text the way `OpenAIClient` does — the idiomatic way to get a
 * schema-conformant structured response from this API, and unlike
 * OpenAI's `json_object` mode, Anthropic's own tool-use `input` is
 * validated against `input_schema` by the API itself before it's ever
 * returned.
 */
final class ClaudeClient implements LLMClientInterface
{
    private const MESSAGES_PATH = '/v1/messages';

    private const API_VERSION = '2023-06-01';

    private const STRUCTURED_OUTPUT_TOOL_NAME = 'return_structured_output';

    private const DEFAULT_MAX_TOKENS = 4096;

    private readonly ClientInterface $http;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-3-opus-20240229',
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => 'https://api.anthropic.com',
            'timeout' => 30,
        ]);
    }

    public function complete(string $prompt, array $options = []): string
    {
        $body = $this->request([
            'model' => $this->model,
            'max_tokens' => self::DEFAULT_MAX_TOKENS,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            ...$options,
        ]);

        $text = $body['content'][0]['text'] ?? null;

        if (! is_string($text)) {
            throw new LLMRequestFailedException('Claude API response did not contain a text content block.');
        }

        return $text;
    }

    public function completeStructured(string $prompt, string $schema, array $options = []): array
    {
        $inputSchema = json_decode($schema, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($inputSchema)) {
            throw new LLMRequestFailedException('LLMPlanner supplied a malformed JSON Schema.');
        }

        $body = $this->request([
            'model' => $this->model,
            'max_tokens' => self::DEFAULT_MAX_TOKENS,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'tools' => [[
                'name' => self::STRUCTURED_OUTPUT_TOOL_NAME,
                'description' => 'Return the structured output for this request.',
                'input_schema' => $inputSchema,
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => self::STRUCTURED_OUTPUT_TOOL_NAME],
            ...$options,
        ]);

        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === self::STRUCTURED_OUTPUT_TOOL_NAME) {
                return is_array($block['input'] ?? null) ? $block['input'] : [];
            }
        }

        throw new LLMRequestFailedException('Claude API response did not contain the expected tool_use block.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(array $payload): array
    {
        try {
            $response = $this->http->request('POST', self::MESSAGES_PATH, [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new LLMRequestFailedException("Claude API request failed: {$e->getMessage()}", previous: $e);
        }

        $decoded = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new LLMRequestFailedException('Claude API returned a malformed (non-JSON-object) response.');
        }

        return $decoded;
    }
}
