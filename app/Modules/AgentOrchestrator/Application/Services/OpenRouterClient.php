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
    /**
     * Deliberately no leading slash — see the constructor's own docblock
     * for why. A leading slash here made every real request silently drop
     * `$baseUrl`'s own `/api/v1` path segment (only caught by a real,
     * live call against openrouter.ai, since every test injects a fake
     * `ClientInterface`/`MockHandler` that never exercises Guzzle's own
     * `base_uri` + relative-URI resolution — HANDOFF §8.95's own predicted
     * gap, hit for real).
     */
    private const CHAT_COMPLETIONS_PATH = 'chat/completions';

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
        /**
         * Guzzle resolves a relative request URI against `base_uri` per
         * RFC 3986 §5.3: a request path starting with `/` is an
         * absolute-path reference and *replaces* `base_uri`'s own path
         * entirely, rather than appending to it — so `base_uri`
         * `https://openrouter.ai/api/v1` + request path
         * `/chat/completions` silently resolved to
         * `https://openrouter.ai/chat/completions` (404/403, `/api/v1`
         * dropped), not `https://openrouter.ai/api/v1/chat/completions`.
         * The fix is the standard Guzzle one: `base_uri` must end with
         * `/` *and* the request path must not start with `/`, so RFC
         * 3986's merge rule appends instead of replacing. `rtrim()` here
         * makes this correct regardless of whether a caller-supplied
         * `$baseUrl` (this constructor's own configurable parameter, its
         * whole reason to exist) already ends with a slash or not.
         */
        $this->http = $http ?? new Client([
            'base_uri' => rtrim($this->baseUrl, '/').'/',
            // 60s, not the 30s every other LLM client/this codebase's own
            // MCPConfig default uses — a real, live free-tier
            // `:free`-suffixed model observed 4.7s-12s for an ordinary
            // call but also two genuine 30s+ timeouts (shared, rate-limited
            // capacity — not this codebase's own bug) in the same session
            // (HANDOFF §7.34/§8.95's live verification). A free model is
            // slower and less consistent than a paid one by nature; this
            // only widens the window before the existing, unconditional
            // LLMPlanner/LLMReasoningEngine fallback kicks in — it does not
            // change what "success" vs. "fallback" means.
            'timeout' => 60,
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
