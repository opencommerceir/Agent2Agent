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
        $this->assertSame('/chat/completions', $sentRequest->getUri()->getPath());
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
