<?php

declare(strict_types=1);

namespace Nexus\Sdk;

/**
 * Official PHP client for the Nexus Public REST API (see /nexus/docs on
 * any Nexus deployment for the full reference this client implements).
 * Zero third-party dependencies (only ext-curl/ext-json, both bundled
 * with PHP) so this package stays trivially installable anywhere — same
 * "don't force a dependency choice on the consumer" reasoning the
 * platform's own REST controllers avoid by never wrapping a specific
 * HTTP client library in their public contract.
 *
 * The HTTP transport is a constructor-injectable callable
 * (`callable(string $method, string $path, array $query): array{status:int, body:string}`)
 * — the same "injectable transport for testability" shape every
 * Guzzle-based connector in the platform itself already uses (see
 * WebhookSender, the LLM providers, etc.), just without requiring Guzzle
 * itself as a dependency here.
 */
final class NexusClient
{
    /** @var callable(string, string, array<string, mixed>): array{status: int, body: string} */
    private $transport;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        ?callable $transport = null,
    ) {
        $this->transport = $transport ?? $this->curlTransport(...);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBusinessProfile(): array
    {
        return $this->get('business');
    }

    /**
     * @return array<string, mixed>
     */
    public function getCatalog(?string $query = null): array
    {
        return $this->get('catalog', $query !== null ? ['query' => $query] : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function searchMarketplace(?string $query = null, ?string $industry = null): array
    {
        $params = array_filter(['query' => $query, 'industry' => $industry], static fn ($v) => $v !== null);

        return $this->get('marketplace/search', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function getNegotiation(int $negotiationId): array
    {
        return $this->get("negotiations/{$negotiationId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function getCreditBalance(): array
    {
        return $this->get('credit/balance');
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function graphql(string $query, array $variables = []): array
    {
        $response = ($this->transport)('POST', '/nexus/api/v1/graphql', [
            'json' => ['query' => $query, 'variables' => $variables],
        ]);

        return $this->decode($response);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $response = ($this->transport)('GET', "/nexus/api/v1/{$path}", ['query' => $query]);

        $decoded = $this->decode($response);

        return $decoded['data'] ?? $decoded;
    }

    /**
     * @param array{status: int, body: string} $response
     * @return array<string, mixed>
     */
    private function decode(array $response): array
    {
        $decoded = json_decode($response['body'], true) ?? [];

        if ($response['status'] >= 400) {
            $error = $decoded['error'] ?? ['code' => 'UNKNOWN', 'message' => 'Request failed.'];

            throw new NexusApiException($response['status'], (string) $error['code'], (string) $error['message']);
        }

        return $decoded;
    }

    /**
     * Verifies a webhook delivery's X-Nexus-Signature header (see
     * /nexus/docs's own Webhooks section) — timing-safe comparison, the
     * same hash_equals() discipline every credential check in the
     * platform itself uses (ApiKey::matches(), AgentToken::matches()).
     */
    public static function verifyWebhookSignature(string $rawBody, string $signatureHeader, string $webhookSecret): bool
    {
        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $webhookSecret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{status: int, body: string}
     */
    private function curlTransport(string $method, string $path, array $options): array
    {
        $url = rtrim($this->baseUrl, '/').$path;

        if (! empty($options['query'])) {
            $url .= '?'.http_build_query($options['query']);
        }

        $ch = curl_init($url);
        $headers = ["Authorization: Bearer {$this->apiKey}", 'Accept: application/json'];

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if (isset($options['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json'], JSON_THROW_ON_ERROR));
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);

        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new NexusApiException(0, 'NETWORK_ERROR', $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $body];
    }
}
