<?php

namespace OpenCommerce\SDK\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenCommerce\SDK\Authentication\AuthenticatedRequest;
use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\Discovery\CapabilityDiscovery;
use OpenCommerce\SDK\Exceptions\AuthenticationException;
use PHPUnit\Framework\TestCase;

/**
 * Uses Guzzle's MockHandler (no real network) via AuthenticatedRequest's
 * optional injectable client — the same seam MCPClient never uses, so
 * production code always talks to a real Guzzle client.
 */
class CapabilityDiscoveryTest extends TestCase
{
    private function discoveryWithFakeResponse(Response $response): CapabilityDiscovery
    {
        $mock = new MockHandler([$response]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $config = new MCPConfig('https://api.example.com/mcp/v1', 'token123');
        $request = new AuthenticatedRequest($config, $client);

        return new CapabilityDiscovery($request);
    }

    public function test_discover_withSuccessfulResponse_returnsListOfCapabilityDtos(): void
    {
        $body = json_encode([
            'data' => ['capabilities' => [
                [
                    'name' => 'commerce.product.search',
                    'description' => 'Search products',
                    'inputSchema' => ['query' => 'string'],
                    'outputSchema' => ['products' => 'array'],
                    'requiredPermissions' => ['commerce.products.read'],
                ],
            ]],
            'meta' => ['count' => 1],
        ]);

        $discovery = $this->discoveryWithFakeResponse(new Response(200, ['Content-Type' => 'application/json'], $body));
        $capabilities = $discovery->discover();

        $this->assertCount(1, $capabilities);
        $this->assertSame('commerce.product.search', $capabilities[0]->name);
    }

    public function test_discover_withEmptyList_returnsEmptyArray(): void
    {
        $body = json_encode(['data' => ['capabilities' => []], 'meta' => ['count' => 0]]);

        $discovery = $this->discoveryWithFakeResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

        $this->assertSame([], $discovery->discover());
    }

    public function test_discover_withUnauthorizedResponse_throwsAuthenticationException(): void
    {
        $body = json_encode(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'invalid token']]);

        $discovery = $this->discoveryWithFakeResponse(new Response(401, ['Content-Type' => 'application/json'], $body));

        $this->expectException(AuthenticationException::class);
        $discovery->discover();
    }

    public function test_discover_sendsBearerTokenHeader(): void
    {
        $body = json_encode(['data' => ['capabilities' => []], 'meta' => ['count' => 0]]);
        $mock = new MockHandler([new Response(200, ['Content-Type' => 'application/json'], $body)]);

        $requestHistory = [];
        $stack = HandlerStack::create($mock);
        $stack->push(\GuzzleHttp\Middleware::history($requestHistory));
        $client = new Client(['handler' => $stack]);

        $config = new MCPConfig('https://api.example.com/mcp/v1', 'token123');
        $request = new AuthenticatedRequest($config, $client);
        (new CapabilityDiscovery($request))->discover();

        $this->assertCount(1, $requestHistory);
        $this->assertSame(
            'Bearer token123',
            $requestHistory[0]['request']->getHeaderLine('Authorization'),
        );
    }
}
