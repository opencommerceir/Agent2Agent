<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\OpenAIClient;
use App\Modules\AgentOrchestrator\Domain\Exceptions\LLMRequestFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * No live OpenAI credentials exist in this dev environment (same
 * reasoning every external Connector's own test in this codebase gives)
 * — every request is intercepted by a Guzzle MockHandler instead, the
 * same injectable-ClientInterface shape WooCommerceClient already
 * established (§7.6), so the real request never leaves this process.
 */
class OpenAIClientTest extends TestCase
{
    public function test_complete_returnsTheMessageContentString(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'Hello from GPT-4']]],
            ])),
        ]);

        $this->assertSame('Hello from GPT-4', $client->complete('Say hello'));
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
            new ConnectException('Connection refused', new Request('POST', '/v1/chat/completions')),
        ]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        $client = new OpenAIClient('sk-test', 'gpt-4', $guzzle);

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

    /**
     * @param list<Response> $responses
     */
    private function clientWithResponses(array $responses): OpenAIClient
    {
        $mock = new MockHandler($responses);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);

        return new OpenAIClient('sk-test', 'gpt-4', $guzzle);
    }
}
