<?php

namespace OpenCommerce\SDK\Authentication;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use OpenCommerce\SDK\Config\MCPConfig;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds one pre-configured Guzzle client (base URL, auth header, timeout,
 * SSL verification) so CapabilityDiscovery and CapabilityExecutor don't
 * each duplicate that setup.
 *
 * Plain Guzzle rather than Laravel's HTTP Client — this is the change
 * that makes the SDK usable from any PHP project, not only one running
 * inside this Laravel application. `http_errors => false` disables
 * Guzzle's own exception-on-4xx/5xx behavior deliberately: MCPException
 * is the SDK's single error path, built from the parsed JSON body, not
 * from Guzzle's generic "the server said 4xx" exception.
 *
 * `http_errors => false` is set on every individual request() call below,
 * not only in the default client's constructor options — an injected
 * client (as the tests do) has its own default options, which don't
 * inherit from what would have been passed to `new Client()` here. Per-
 * request options always win regardless of the client's own defaults, so
 * this guarantees the "never throw on 4xx/5xx" behavior for any client,
 * injected or not.
 *
 * Accepts an optional pre-built ClientInterface so tests can inject a
 * Guzzle MockHandler-backed client instead of hitting real network —
 * MCPClient itself never passes one (production always gets a real
 * client), keeping `new MCPClient($config)` clean for real usage.
 */
final class AuthenticatedRequest
{
    private readonly ClientInterface $client;

    private readonly TokenAuthenticator $authenticator;

    public function __construct(
        private readonly MCPConfig $config,
        ?ClientInterface $client = null,
    ) {
        $this->authenticator = new TokenAuthenticator($config->token);
        $this->client = $client ?? new Client([
            'base_uri' => rtrim($config->baseUrl, '/').'/',
            'timeout' => $config->timeout,
            'verify' => $config->verifySSL,
            'http_errors' => false,
        ]);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function get(string $uri): array
    {
        $response = $this->client->request('GET', $uri, [
            'headers' => $this->authenticator->headers(),
            'http_errors' => false,
        ]);

        return $this->toResult($response);
    }

    /**
     * @param array<string, mixed> $json
     * @return array{status: int, body: array<string, mixed>}
     */
    public function post(string $uri, array $json): array
    {
        $response = $this->client->request('POST', $uri, [
            'headers' => $this->authenticator->headers(),
            'json' => $json,
            'http_errors' => false,
        ]);

        return $this->toResult($response);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function toResult(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return [
            'status' => $response->getStatusCode(),
            'body' => is_array($decoded) ? $decoded : [],
        ];
    }
}
