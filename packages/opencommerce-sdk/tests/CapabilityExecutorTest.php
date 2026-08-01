<?php

namespace OpenCommerce\SDK\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenCommerce\SDK\Authentication\AuthenticatedRequest;
use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\DTOs\CapabilityInput;
use OpenCommerce\SDK\Exceptions\AuthorizationException;
use OpenCommerce\SDK\Exceptions\NotFoundException;
use OpenCommerce\SDK\Exceptions\ValidationException;
use OpenCommerce\SDK\Execution\CapabilityExecutor;
use PHPUnit\Framework\TestCase;

class CapabilityExecutorTest extends TestCase
{
    private function executorWithFakeResponse(Response $response): CapabilityExecutor
    {
        $mock = new MockHandler([$response]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $config = new MCPConfig('https://api.example.com/mcp/v1', 'token123');

        return new CapabilityExecutor(new AuthenticatedRequest($config, $client));
    }

    public function test_execute_withSuccessfulResponse_returnsExecutionResult(): void
    {
        $body = json_encode([
            'data' => ['message' => 'ok', 'products' => []],
            'meta' => ['capability' => 'commerce.product.search', 'execution_time' => 5],
        ]);

        $executor = $this->executorWithFakeResponse(new Response(200, ['Content-Type' => 'application/json'], $body));
        $result = $executor->execute('commerce.product.search', CapabilityInput::fromArray(['query' => 'laptop']));

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->getError());
        $this->assertSame('ok', $result->getData()['message']);
        $this->assertSame('commerce.product.search', $result->getMeta()['capability']);
    }

    public function test_execute_withForbiddenResponse_throwsAuthorizationException(): void
    {
        $body = json_encode(['error' => ['code' => 'FORBIDDEN', 'message' => 'Agent lacks required permission.']]);

        $executor = $this->executorWithFakeResponse(new Response(403, ['Content-Type' => 'application/json'], $body));

        $this->expectException(AuthorizationException::class);
        $executor->execute('commerce.product.search', CapabilityInput::fromArray(['query' => 'laptop']));
    }

    public function test_execute_withUnknownCapability_throwsNotFoundException(): void
    {
        $body = json_encode(['error' => ['code' => 'NOT_FOUND', 'message' => 'Capability does not exist.']]);

        $executor = $this->executorWithFakeResponse(new Response(404, ['Content-Type' => 'application/json'], $body));

        $this->expectException(NotFoundException::class);
        $executor->execute('commerce.nonexistent', CapabilityInput::fromArray([]));
    }

    public function test_execute_withInvalidInput_throwsValidationException(): void
    {
        $body = json_encode(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Missing required input field [query].']]);

        $executor = $this->executorWithFakeResponse(new Response(422, ['Content-Type' => 'application/json'], $body));

        $this->expectException(ValidationException::class);
        $executor->execute('commerce.product.search', CapabilityInput::fromArray([]));
    }

    public function test_execute_withV2Envelope_readsResultAndMetadataInstead(): void
    {
        $body = json_encode([
            'result' => ['message' => 'ok'],
            'metadata' => ['api_version' => 'v2', 'capability' => 'commerce.product.search'],
        ]);

        $executor = $this->executorWithFakeResponse(new Response(200, ['Content-Type' => 'application/json'], $body));
        $result = $executor->execute('commerce.product.search', CapabilityInput::fromArray(['query' => 'laptop']));

        $this->assertSame('ok', $result->getData()['message']);
        $this->assertSame('v2', $result->getMeta()['api_version']);
    }

    public function test_execute_sendsCapabilityAndInputAsJsonBody(): void
    {
        $body = json_encode(['data' => [], 'meta' => []]);
        $mock = new MockHandler([new Response(200, ['Content-Type' => 'application/json'], $body)]);

        $requestHistory = [];
        $stack = HandlerStack::create($mock);
        $stack->push(\GuzzleHttp\Middleware::history($requestHistory));
        $client = new Client(['handler' => $stack]);

        $config = new MCPConfig('https://api.example.com/mcp/v1', 'token123');
        (new CapabilityExecutor(new AuthenticatedRequest($config, $client)))
            ->execute('commerce.product.search', CapabilityInput::fromArray(['query' => 'laptop']));

        $sentBody = json_decode((string) $requestHistory[0]['request']->getBody(), true);

        $this->assertSame('commerce.product.search', $sentBody['capability']);
        $this->assertSame(['query' => 'laptop'], $sentBody['input']);
    }
}
