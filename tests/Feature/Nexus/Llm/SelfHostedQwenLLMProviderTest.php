<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Infrastructure\Providers\AbstractOpenAiCompatibleProvider;
use App\Domains\Nexus\Llm\Infrastructure\Providers\SelfHostedQwenLLMProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use ReflectionProperty;
use Tests\TestCase;

/**
 * No real local model server runs in this dev environment (same honest
 * limitation every other external Connector in this codebase documents) —
 * every request is intercepted by a Guzzle MockHandler with a canned
 * Ollama-shaped JSON fixture, never a live localhost:11434 call.
 */
class SelfHostedQwenLLMProviderTest extends TestCase
{
    private function clientWithResponses(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }

    public function test_chat_onSuccess_isAlwaysFreeAndExtractsContent(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'model' => 'qwen2.5:14b',
                'choices' => [['message' => ['content' => 'local reply']]],
                'usage' => ['prompt_tokens' => 60, 'completion_tokens' => 30],
            ])),
        ]);
        $provider = new SelfHostedQwenLLMProvider('', 'qwen2.5:14b', 'http://localhost:11434/v1', $guzzle);

        $response = $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('local reply', $response->content);
        $this->assertSame('qwen-14b-local', $response->provider);
        $this->assertSame(0.0, $response->estimatedCost);
    }

    public function test_chat_onUnreachableServer_throws(): void
    {
        $guzzle = $this->clientWithResponses([
            new RequestException('Connection refused', new Request('POST', 'chat/completions')),
        ]);
        $provider = new SelfHostedQwenLLMProvider('', 'qwen2.5:14b', 'http://localhost:11434/v1', $guzzle);

        $this->expectException(LLMProviderRequestFailedException::class);

        $provider->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_estimateCost_isAlwaysZeroRegardlessOfMessageSize(): void
    {
        $provider = new SelfHostedQwenLLMProvider('', 'qwen2.5:14b', 'http://localhost:11434/v1', new Client());

        $this->assertSame(0.0, $provider->estimateCost([['role' => 'user', 'content' => str_repeat('a', 50000)]]));
    }

    public function test_supports_reasoningNegotiationAndClassificationButNotFallback(): void
    {
        $provider = new SelfHostedQwenLLMProvider('', 'qwen2.5:14b', 'http://localhost:11434/v1', new Client());

        $this->assertTrue($provider->supports(LLMFeature::Reasoning));
        $this->assertTrue($provider->supports(LLMFeature::Negotiation));
        $this->assertTrue($provider->supports(LLMFeature::Classification));
        $this->assertFalse($provider->supports(LLMFeature::Fallback));
    }

    public function test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint(): void
    {
        $provider = new SelfHostedQwenLLMProvider('', 'qwen2.5:14b', 'http://localhost:11434/v1');

        $property = new ReflectionProperty(AbstractOpenAiCompatibleProvider::class, 'http');
        $property->setAccessible(true);
        $guzzle = $property->getValue($provider);

        $resolved = UriResolver::resolve(
            Utils::uriFor($guzzle->getConfig('base_uri')),
            Utils::uriFor('chat/completions'),
        );

        $this->assertSame('http://localhost:11434/v1/chat/completions', (string) $resolved);
    }
}
