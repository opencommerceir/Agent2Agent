<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Infrastructure\Providers\AbstractOpenAiCompatibleProvider;
use App\Domains\Nexus\Llm\Infrastructure\Providers\GroqLLMProvider;
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
 * No live Groq credentials/network access assumed — every request is
 * intercepted by a Guzzle MockHandler, same discipline every external
 * Connector's own test in this codebase already uses.
 */
class GroqLLMProviderTest extends TestCase
{
    private function clientWithResponses(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }

    public function test_chat_onSuccess_isAlwaysFreeAndExtractsContent(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'model' => 'llama-3.1-8b-instant',
                'choices' => [['message' => ['content' => 'fast reply']]],
                'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 8],
            ])),
        ]);
        $provider = new GroqLLMProvider('gsk-test', 'llama-3.1-8b-instant', 'https://api.groq.com/openai/v1', $guzzle);

        $response = $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('fast reply', $response->content);
        $this->assertSame('groq', $response->provider);
        $this->assertSame(0.0, $response->estimatedCost);
    }

    public function test_chat_onGuzzleFailure_throws(): void
    {
        $guzzle = $this->clientWithResponses([
            new RequestException('rate limited', new Request('POST', 'chat/completions')),
        ]);
        $provider = new GroqLLMProvider('gsk-test', 'm', 'https://api.groq.com/openai/v1', $guzzle);

        $this->expectException(LLMProviderRequestFailedException::class);

        $provider->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_estimateCost_isAlwaysZero(): void
    {
        $provider = new GroqLLMProvider('gsk-test', 'm', 'https://api.groq.com/openai/v1', new Client());

        $this->assertSame(0.0, $provider->estimateCost([['role' => 'user', 'content' => str_repeat('a', 5000)]]));
    }

    public function test_supports_returnsTrueForEveryFeature(): void
    {
        $provider = new GroqLLMProvider('gsk-test', 'm', 'https://api.groq.com/openai/v1', new Client());

        foreach (LLMFeature::cases() as $feature) {
            $this->assertTrue($provider->supports($feature));
        }
    }

    public function test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint(): void
    {
        $provider = new GroqLLMProvider('gsk-test', 'm', 'https://api.groq.com/openai/v1');

        $property = new ReflectionProperty(AbstractOpenAiCompatibleProvider::class, 'http');
        $property->setAccessible(true);
        $guzzle = $property->getValue($provider);

        $resolved = UriResolver::resolve(
            Utils::uriFor($guzzle->getConfig('base_uri')),
            Utils::uriFor('chat/completions'),
        );

        $this->assertSame('https://api.groq.com/openai/v1/chat/completions', (string) $resolved);
    }
}
