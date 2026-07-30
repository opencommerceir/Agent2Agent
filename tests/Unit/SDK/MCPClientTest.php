<?php

namespace Tests\Unit\SDK;

use App\SDK\Exceptions\AuthenticationException;
use App\SDK\Exceptions\AuthorizationException;
use App\SDK\Exceptions\NotFoundException;
use App\SDK\Exceptions\ValidationException;
use App\SDK\MCPClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises MCPClient end to end against Http::fake() — real HTTP-client
 * plumbing (headers, base URL, JSON decoding) without a real network call
 * or a real MCP Gateway. This necessarily boots Laravel (Http::fake()
 * requires it), unlike Tests\Unit\Core, which is framework-free — the SDK
 * is built on Illuminate\Support\Facades\Http, so its tests inherit that.
 */
class MCPClientTest extends TestCase
{
    public function test_discoverCapabilities_returnsListOfCapabilityDtos(): void
    {
        Http::fake([
            '*/capabilities' => Http::response([
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
            ], 200),
        ]);

        $client = new MCPClient('https://api.example.com/mcp/v1', 'token123');
        $capabilities = $client->discoverCapabilities();

        $this->assertCount(1, $capabilities);
        $this->assertSame('commerce.product.search', $capabilities[0]->name);
    }

    public function test_execute_withSuccessfulResponse_returnsExecutionResult(): void
    {
        Http::fake([
            '*/execute' => Http::response([
                'data' => ['message' => 'ok', 'products' => []],
                'meta' => ['capability' => 'commerce.product.search', 'execution_time' => 5],
            ], 200),
        ]);

        $client = new MCPClient('https://api.example.com/mcp/v1', 'token123');
        $result = $client->execute('commerce.product.search', ['query' => 'laptop']);

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->getError());
        $this->assertSame('ok', $result->getData()['message']);
        $this->assertSame('commerce.product.search', $result->getMeta()['capability']);
    }

    public function test_execute_withInvalidToken_throwsAuthenticationException(): void
    {
        Http::fake([
            '*/execute' => Http::response([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'The provided agent token is invalid, revoked, or expired.'],
            ], 401),
        ]);

        $client = new MCPClient('https://api.example.com/mcp/v1', 'invalid-token');

        $this->expectException(AuthenticationException::class);
        $client->execute('commerce.product.search', ['query' => 'laptop']);
    }

    public function test_execute_withMissingPermission_throwsAuthorizationException(): void
    {
        Http::fake([
            '*/execute' => Http::response([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Agent lacks required permission.'],
            ], 403),
        ]);

        $client = new MCPClient('https://api.example.com/mcp/v1', 'token123');

        $this->expectException(AuthorizationException::class);
        $client->execute('commerce.product.search', ['query' => 'laptop']);
    }

    public function test_execute_withUnknownCapability_throwsNotFoundException(): void
    {
        Http::fake([
            '*/execute' => Http::response([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Capability [x] does not exist.'],
            ], 404),
        ]);

        $client = new MCPClient('https://api.example.com/mcp/v1', 'token123');

        $this->expectException(NotFoundException::class);
        $client->execute('commerce.nonexistent', []);
    }

    public function test_execute_withInvalidInput_throwsValidationException(): void
    {
        Http::fake([
            '*/execute' => Http::response([
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Missing required input field [query].'],
            ], 422),
        ]);

        $client = new MCPClient('https://api.example.com/mcp/v1', 'token123');

        $this->expectException(ValidationException::class);
        $client->execute('commerce.product.search', []);
    }

    public function test_getCapability_withKnownName_returnsMatchingCapability(): void
    {
        Http::fake([
            '*/capabilities' => Http::response([
                'data' => ['capabilities' => [
                    ['name' => 'commerce.product.search', 'description' => 'x', 'inputSchema' => [], 'outputSchema' => [], 'requiredPermissions' => []],
                ]],
                'meta' => ['count' => 1],
            ], 200),
        ]);

        $client = new MCPClient('https://api.example.com/mcp/v1', 'token123');
        $capability = $client->getCapability('commerce.product.search');

        $this->assertSame('commerce.product.search', $capability->name);
    }

    public function test_getCapability_withUnknownName_throwsNotFoundException(): void
    {
        Http::fake([
            '*/capabilities' => Http::response(['data' => ['capabilities' => []], 'meta' => ['count' => 0]], 200),
        ]);

        $client = new MCPClient('https://api.example.com/mcp/v1', 'token123');

        $this->expectException(NotFoundException::class);
        $client->getCapability('commerce.nonexistent');
    }

    public function test_client_sendsBearerTokenHeaderOnEveryRequest(): void
    {
        Http::fake([
            '*' => Http::response(['data' => ['capabilities' => []], 'meta' => ['count' => 0]], 200),
        ]);

        (new MCPClient('https://api.example.com/mcp/v1', 'my-secret-token'))->discoverCapabilities();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer my-secret-token'));
    }
}
