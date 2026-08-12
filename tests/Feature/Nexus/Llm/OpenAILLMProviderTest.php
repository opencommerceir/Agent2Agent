<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Infrastructure\Providers\OpenAILLMProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * No live OpenAI credentials/network access assumed — every request is
 * intercepted by a Guzzle MockHandler, same discipline every external
 * Connector's own test in this codebase already uses (see
 * ZibalPaymentGatewayTest).
 */
class OpenAILLMProviderTest extends TestCase
{
    private function clientWithResponses(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }

    public function test_chat_onSuccess_extractsContentTokensAndComputesCost(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'model' => 'gpt-4o',
                'choices' => [['message' => ['content' => 'hello there']]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ])),
        ]);
        $provider = new OpenAILLMProvider('sk-test', 'gpt-4o', 'https://api.openai.com/v1', $guzzle);

        $response = $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('hello there', $response->content);
        $this->assertSame('openai', $response->provider);
        $this->assertSame('gpt-4o', $response->model);
        $this->assertSame(100, $response->promptTokens);
        $this->assertSame(50, $response->completionTokens);
        $this->assertSame(150, $response->totalTokens);
        $this->assertGreaterThan(0.0, $response->estimatedCost);
        $this->assertFalse($response->fromFallback);
    }

    public function test_chat_onGuzzleFailure_throwsLLMProviderRequestFailedException(): void
    {
        $guzzle = $this->clientWithResponses([
            new RequestException('connection refused', new Request('POST', 'chat/completions')),
        ]);
        $provider = new OpenAILLMProvider('sk-test', 'gpt-4o', 'https://api.openai.com/v1', $guzzle);

        $this->expectException(LLMProviderRequestFailedException::class);

        $provider->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_onMissingContent_throwsLLMProviderRequestFailedException(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['choices' => [['message' => []]]])),
        ]);
        $provider = new OpenAILLMProvider('sk-test', 'gpt-4o', 'https://api.openai.com/v1', $guzzle);

        $this->expectException(LLMProviderRequestFailedException::class);

        $provider->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_estimateCost_isPositiveForNonEmptyMessages(): void
    {
        $provider = new OpenAILLMProvider('sk-test', 'gpt-4o', 'https://api.openai.com/v1', new Client());

        $cost = $provider->estimateCost([['role' => 'user', 'content' => str_repeat('a', 4000)]]);

        $this->assertGreaterThan(0.0, $cost);
    }

    public function test_supports_returnsTrueForEveryFeature(): void
    {
        $provider = new OpenAILLMProvider('sk-test', 'gpt-4o', 'https://api.openai.com/v1', new Client());

        foreach (LLMFeature::cases() as $feature) {
            $this->assertTrue($provider->supports($feature));
        }
    }

    public function test_request_sendsRelativePathWithNoLeadingSlash(): void
    {
        // No `base_uri` on this test's own Guzzle client (unlike the
        // constructor's own `$http ??= new Client([...])` branch) — this
        // isolates the literal path argument passed to `$http->request()`,
        // same convention `ZibalPaymentGatewayTest::test_request_sendsCorrectPath()`
        // already uses, since a leading slash here would silently drop
        // `base_uri`'s own `/v1` segment (the real, historical
        // OpenRouterClient bug this codebase already documents).
        $container = [];
        $mock = new MockHandler([new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'x']]]]))]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($container));
        $guzzle = new Client(['handler' => $handlerStack]);

        $provider = new OpenAILLMProvider('sk-test', 'gpt-4o', 'https://api.openai.com/v1', $guzzle);
        $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('chat/completions', $container[0]['request']->getUri()->getPath());
    }

    /**
     * Reaches the constructor's own default `$http ??= new Client([...])`
     * branch via reflection (no network access) and resolves the real
     * request URI the same way Guzzle does internally, so the
     * OpenRouterClient-class bug (base_uri silently dropping a path
     * segment) can't come back unnoticed here — same technique
     * `ZibalPaymentGatewayTest::test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint()`
     * already uses.
     */
    public function test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint(): void
    {
        $provider = new OpenAILLMProvider('sk-test', 'gpt-4o', 'https://api.openai.com/v1');

        $property = new \ReflectionProperty(\App\Domains\Nexus\Llm\Infrastructure\Providers\AbstractOpenAiCompatibleProvider::class, 'http');
        $property->setAccessible(true);
        $guzzle = $property->getValue($provider);

        $resolved = \GuzzleHttp\Psr7\UriResolver::resolve(
            \GuzzleHttp\Psr7\Utils::uriFor($guzzle->getConfig('base_uri')),
            \GuzzleHttp\Psr7\Utils::uriFor('chat/completions'),
        );

        $this->assertSame('https://api.openai.com/v1/chat/completions', (string) $resolved);
    }
}
