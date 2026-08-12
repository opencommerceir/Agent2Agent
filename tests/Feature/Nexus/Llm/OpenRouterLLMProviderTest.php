<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Infrastructure\Providers\AbstractOpenAiCompatibleProvider;
use App\Domains\Nexus\Llm\Infrastructure\Providers\OpenRouterLLMProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use ReflectionProperty;
use Tests\TestCase;

/**
 * No live OpenRouter credentials/network access assumed — every request is
 * intercepted by a Guzzle MockHandler, same discipline every external
 * Connector's own test in this codebase already uses.
 */
class OpenRouterLLMProviderTest extends TestCase
{
    private function clientWithResponses(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }

    public function test_chat_onSuccess_isAlwaysFreeAndExtractsContent(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'model' => 'meta-llama/llama-3.1-405b-instruct:free',
                'choices' => [['message' => ['content' => 'free reply']]],
                'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 20],
            ])),
        ]);
        $provider = new OpenRouterLLMProvider('sk-test', 'meta-llama/llama-3.1-405b-instruct:free', 'https://openrouter.ai/api/v1', $guzzle);

        $response = $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('free reply', $response->content);
        $this->assertSame('openrouter', $response->provider);
        $this->assertSame(0.0, $response->estimatedCost);
    }

    public function test_chat_onGuzzleFailure_throws(): void
    {
        $guzzle = $this->clientWithResponses([
            new RequestException('timeout', new Request('POST', 'chat/completions')),
        ]);
        $provider = new OpenRouterLLMProvider('sk-test', 'm', 'https://openrouter.ai/api/v1', $guzzle);

        $this->expectException(LLMProviderRequestFailedException::class);

        $provider->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_estimateCost_isAlwaysZero(): void
    {
        $provider = new OpenRouterLLMProvider('sk-test', 'm', 'https://openrouter.ai/api/v1', new Client());

        $this->assertSame(0.0, $provider->estimateCost([['role' => 'user', 'content' => str_repeat('a', 5000)]]));
    }

    public function test_supports_returnsTrueForEveryFeature(): void
    {
        $provider = new OpenRouterLLMProvider('sk-test', 'm', 'https://openrouter.ai/api/v1', new Client());

        foreach (LLMFeature::cases() as $feature) {
            $this->assertTrue($provider->supports($feature));
        }
    }

    /**
     * Same base_uri-path-segment regression guard as OpenAILLMProviderTest
     * — OpenRouter's base_url carries its own `/api/v1` segment, exactly
     * the shape that silently broke in AgentOrchestrator's own
     * OpenRouterClient before it was fixed.
     */
    public function test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint(): void
    {
        $provider = new OpenRouterLLMProvider('sk-test', 'm', 'https://openrouter.ai/api/v1');

        $property = new ReflectionProperty(AbstractOpenAiCompatibleProvider::class, 'http');
        $property->setAccessible(true);
        $guzzle = $property->getValue($provider);

        $resolved = UriResolver::resolve(
            Utils::uriFor($guzzle->getConfig('base_uri')),
            Utils::uriFor('chat/completions'),
        );

        $this->assertSame('https://openrouter.ai/api/v1/chat/completions', (string) $resolved);
    }
}
