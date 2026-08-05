<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\ClaudeClient;
use App\Modules\AgentOrchestrator\Domain\Exceptions\LLMRequestFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * No live Anthropic credentials exist in this dev environment — see
 * OpenAIClientTest's own docblock for the identical reasoning.
 */
class ClaudeClientTest extends TestCase
{
    public function test_complete_returnsTheTextContentBlock(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'content' => [['type' => 'text', 'text' => 'Hello from Claude']],
            ])),
        ]);

        $this->assertSame('Hello from Claude', $client->complete('Say hello'));
    }

    public function test_completeStructured_returnsTheToolUseInputBlock(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'content' => [
                    ['type' => 'tool_use', 'name' => 'return_structured_output', 'input' => ['steps' => [['capability' => 'x', 'input' => []]]]],
                ],
            ])),
        ]);

        $result = $client->completeStructured('plan this', '{"type":"object","properties":{"steps":{"type":"array"}}}');

        $this->assertSame([['capability' => 'x', 'input' => []]], $result['steps']);
    }

    public function test_completeStructured_throwsWhenNoToolUseBlockIsPresent(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'content' => [['type' => 'text', 'text' => 'I refuse to use the tool.']],
            ])),
        ]);

        $this->expectException(LLMRequestFailedException::class);

        $client->completeStructured('plan this', '{"type":"object"}');
    }

    public function test_completeStructured_throwsWhenGivenAMalformedSchema(): void
    {
        $client = $this->clientWithResponses([]);

        $this->expectException(LLMRequestFailedException::class);

        $client->completeStructured('plan this', 'not json');
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
    private function clientWithResponses(array $responses): ClaudeClient
    {
        $mock = new MockHandler($responses);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);

        return new ClaudeClient('sk-ant-test', 'claude-3-opus-20240229', $guzzle);
    }
}
