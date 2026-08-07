<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\OpenRouterClient;
use App\Modules\AgentOrchestrator\Domain\Exceptions\LLMRequestFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * No live OpenRouter credentials exist in this dev environment (same
 * reasoning `OpenAIClientTest`/every external Connector's own test in this
 * codebase gives) — every request is intercepted by a Guzzle MockHandler
 * instead, so the real request never leaves this process.
 */
class OpenRouterClientTest extends TestCase
{
    public function test_complete_returnsTheMessageContentString(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'Hello from Llama']]],
            ])),
        ]);

        $this->assertSame('Hello from Llama', $client->complete('Say hello'));
    }

    public function test_completeStructured_decodesTheModelsOwnJsonContent(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => json_encode(['steps' => [['capability' => 'x', 'input' => []]]])]]],
            ])),
        ]);

        $result = $client->completeStructured('plan this', '{"type":"object"}');

        $this->assertSame([['capability' => 'x', 'input' => []]], $result['steps']);
    }

    public function test_completeStructured_throwsWhenTheModelsOwnContentIsNotValidJson(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'not json at all']]],
            ])),
        ]);

        $this->expectException(LLMRequestFailedException::class);

        $client->completeStructured('plan this', '{"type":"object"}');
    }

    public function test_complete_throwsWhenTheHttpRequestFails(): void
    {
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', '/chat/completions')),
        ]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        $client = new OpenRouterClient('sk-or-test', 'meta-llama/llama-3.1-405b-instruct:free', $guzzle);

        $this->expectException(LLMRequestFailedException::class);

        $client->complete('Say hello');
    }

    public function test_complete_throwsWhenTheResponseBodyIsMalformed(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], 'not json'),
        ]);

        $this->expectException(LLMRequestFailedException::class);

        $client->complete('Say hello');
    }

    public function test_defaultModel_isTheFreeLlama405bInstruct(): void
    {
        $container = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'ok']]]])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($container));
        $guzzle = new Client(['handler' => $handlerStack]);

        $client = new OpenRouterClient('sk-or-test', http: $guzzle);
        $client->complete('Say hello');

        $sentBody = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertSame('meta-llama/llama-3.1-405b-instruct:free', $sentBody['model']);
    }

    public function test_request_sendsAttributionHeadersAndBearerToken(): void
    {
        $container = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'ok']]]])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($container));
        $guzzle = new Client(['handler' => $handlerStack]);

        $client = new OpenRouterClient('sk-or-test', http: $guzzle);
        $client->complete('Say hello');

        $sentRequest = $container[0]['request'];
        $this->assertSame('Bearer sk-or-test', $sentRequest->getHeaderLine('Authorization'));
        $this->assertSame('OpenCommerce Platform', $sentRequest->getHeaderLine('X-Title'));
        $this->assertNotEmpty($sentRequest->getHeaderLine('HTTP-Referer'));
        // No leading slash — a real Guzzle `base_uri` merge requires the
        // relative request path to omit it, or `$baseUrl`'s own `/api/v1`
        // segment silently gets dropped on every real request (see the
        // constructor's own docblock, and HANDOFF §7.34/§8.95).
        $this->assertSame('chat/completions', $sentRequest->getUri()->getPath());
    }

    /**
     * Every other test above injects `$http` directly, which bypasses the
     * constructor's own `$http ??= new Client(['base_uri' => ...])`
     * branch entirely — so none of them exercised the real `$baseUrl` +
     * relative-path Guzzle resolution a live request actually depends on.
     * That untested branch is exactly what silently dropped `/api/v1`
     * from every real OpenRouter request before this fix (only caught by
     * an actual, live call — HANDOFF §7.34/§8.95). This test reaches that
     * branch via reflection (no network access) and resolves the real
     * request URI the same way Guzzle does internally, so this exact
     * regression can't come back unnoticed.
     */
    public function test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint(): void
    {
        $client = new OpenRouterClient('sk-or-test');

        $property = new \ReflectionProperty($client, 'http');
        $property->setAccessible(true);
        $guzzle = $property->getValue($client);

        $resolved = \GuzzleHttp\Psr7\UriResolver::resolve(
            \GuzzleHttp\Psr7\Utils::uriFor($guzzle->getConfig('base_uri')),
            \GuzzleHttp\Psr7\Utils::uriFor('chat/completions'),
        );

        $this->assertSame('https://openrouter.ai/api/v1/chat/completions', (string) $resolved);
    }

    /**
     * @param list<Response> $responses
     */
    private function clientWithResponses(array $responses): OpenRouterClient
    {
        $mock = new MockHandler($responses);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);

        return new OpenRouterClient('sk-or-test', 'meta-llama/llama-3.1-405b-instruct:free', $guzzle);
    }
}
