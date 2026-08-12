<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Infrastructure\Providers\AbstractOpenAiCompatibleProvider;
use App\Domains\Nexus\Llm\Infrastructure\Providers\LocalLlamaLLMProvider;
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
 * Same "no real local model server, MockHandler fixtures only" reasoning
 * as SelfHostedQwenLLMProviderTest.
 */
class LocalLlamaLLMProviderTest extends TestCase
{
    private function clientWithResponses(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }

    public function test_chat_onSuccess_isAlwaysFreeAndExtractsContent(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'model' => 'llama3.2:3b',
                'choices' => [['message' => ['content' => 'classification: order_inquiry']]],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 5],
            ])),
        ]);
        $provider = new LocalLlamaLLMProvider('', 'llama3.2:3b', 'http://localhost:11434/v1', $guzzle);

        $response = $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('classification: order_inquiry', $response->content);
        $this->assertSame('llama-3.2-3b-local', $response->provider);
        $this->assertSame(0.0, $response->estimatedCost);
    }

    public function test_chat_onUnreachableServer_throws(): void
    {
        $guzzle = $this->clientWithResponses([
            new RequestException('Connection refused', new Request('POST', 'chat/completions')),
        ]);
        $provider = new LocalLlamaLLMProvider('', 'llama3.2:3b', 'http://localhost:11434/v1', $guzzle);

        $this->expectException(LLMProviderRequestFailedException::class);

        $provider->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_estimateCost_isAlwaysZero(): void
    {
        $provider = new LocalLlamaLLMProvider('', 'llama3.2:3b', 'http://localhost:11434/v1', new Client());

        $this->assertSame(0.0, $provider->estimateCost([['role' => 'user', 'content' => str_repeat('a', 5000)]]));
    }

    public function test_supports_onlyClassification(): void
    {
        $provider = new LocalLlamaLLMProvider('', 'llama3.2:3b', 'http://localhost:11434/v1', new Client());

        $this->assertTrue($provider->supports(LLMFeature::Classification));
        $this->assertFalse($provider->supports(LLMFeature::Reasoning));
        $this->assertFalse($provider->supports(LLMFeature::Negotiation));
        $this->assertFalse($provider->supports(LLMFeature::Fallback));
    }

    public function test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint(): void
    {
        $provider = new LocalLlamaLLMProvider('', 'llama3.2:3b', 'http://localhost:11434/v1');

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
