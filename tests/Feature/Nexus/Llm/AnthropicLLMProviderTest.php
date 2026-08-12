<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Infrastructure\Providers\AnthropicLLMProvider;
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
 * No live Anthropic credentials/network access assumed — every request is
 * intercepted by a Guzzle MockHandler, same discipline every external
 * Connector's own test in this codebase already uses. Fixtures use
 * Anthropic's own Messages API response shape (content[0].text,
 * usage.input_tokens/output_tokens) — deliberately different from every
 * other provider test's OpenAI-Chat-Completions-shaped fixtures.
 */
class AnthropicLLMProviderTest extends TestCase
{
    private function clientWithResponses(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }

    public function test_chat_onSuccess_extractsTextTokensAndComputesCost(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'model' => 'claude-3-opus-20240229',
                'content' => [['type' => 'text', 'text' => 'hello from claude']],
                'usage' => ['input_tokens' => 80, 'output_tokens' => 40],
            ])),
        ]);
        $provider = new AnthropicLLMProvider('sk-ant-test', 'claude-3-opus-20240229', 'https://api.anthropic.com', $guzzle);

        $response = $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('hello from claude', $response->content);
        $this->assertSame('claude', $response->provider);
        $this->assertSame(80, $response->promptTokens);
        $this->assertSame(40, $response->completionTokens);
        $this->assertSame(120, $response->totalTokens);
        $this->assertGreaterThan(0.0, $response->estimatedCost);
    }

    public function test_chat_onGuzzleFailure_throws(): void
    {
        $guzzle = $this->clientWithResponses([
            new RequestException('auth error', new Request('POST', 'v1/messages')),
        ]);
        $provider = new AnthropicLLMProvider('sk-ant-test', 'claude-3-opus-20240229', 'https://api.anthropic.com', $guzzle);

        $this->expectException(LLMProviderRequestFailedException::class);

        $provider->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_onMissingTextBlock_throws(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['content' => []])),
        ]);
        $provider = new AnthropicLLMProvider('sk-ant-test', 'claude-3-opus-20240229', 'https://api.anthropic.com', $guzzle);

        $this->expectException(LLMProviderRequestFailedException::class);

        $provider->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_estimateCost_isPositiveForNonEmptyMessages(): void
    {
        $provider = new AnthropicLLMProvider('sk-ant-test', 'claude-3-opus-20240229', 'https://api.anthropic.com', new Client());

        $this->assertGreaterThan(0.0, $provider->estimateCost([['role' => 'user', 'content' => str_repeat('a', 4000)]]));
    }

    public function test_supports_returnsTrueForEveryFeature(): void
    {
        $provider = new AnthropicLLMProvider('sk-ant-test', 'claude-3-opus-20240229', 'https://api.anthropic.com', new Client());

        foreach (LLMFeature::cases() as $feature) {
            $this->assertTrue($provider->supports($feature));
        }
    }

    public function test_request_sendsCorrectHeaders(): void
    {
        $container = [];
        $mock = new MockHandler([new Response(200, [], json_encode(['content' => [['type' => 'text', 'text' => 'x']]]))]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($container));
        $guzzle = new Client(['handler' => $handlerStack]);

        $provider = new AnthropicLLMProvider('sk-ant-test', 'claude-3-opus-20240229', 'https://api.anthropic.com', $guzzle);
        $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $request = $container[0]['request'];
        $this->assertSame('sk-ant-test', $request->getHeaderLine('x-api-key'));
        $this->assertSame('2023-06-01', $request->getHeaderLine('anthropic-version'));
        $this->assertSame('v1/messages', $request->getUri()->getPath());
    }

    public function test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint(): void
    {
        $provider = new AnthropicLLMProvider('sk-ant-test', 'claude-3-opus-20240229', 'https://api.anthropic.com');

        $property = new ReflectionProperty(AnthropicLLMProvider::class, 'http');
        $property->setAccessible(true);
        $guzzle = $property->getValue($provider);

        $resolved = UriResolver::resolve(
            Utils::uriFor($guzzle->getConfig('base_uri')),
            Utils::uriFor('v1/messages'),
        );

        $this->assertSame('https://api.anthropic.com/v1/messages', (string) $resolved);
    }
}
