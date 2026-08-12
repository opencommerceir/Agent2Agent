<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Admin\Application\Services\MarginSettingsService;
use App\Domains\Nexus\Admin\Domain\Repositories\PlatformSettingRepositoryInterface;
use App\Domains\Nexus\Llm\Application\Services\LLMBudgetGuard;
use App\Domains\Nexus\Llm\Application\Services\LLMProviderRegistry;
use App\Domains\Nexus\Llm\Application\Services\LLMRouter;
use App\Domains\Nexus\Llm\Application\Services\LLMSettingsService;
use App\Domains\Nexus\Llm\Domain\Exceptions\AllLLMProvidersFailedException;
use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\Repositories\LLMUsageLogRepositoryInterface;
use App\Domains\Nexus\Llm\Domain\Services\LLMProviderInterface;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fake LLMProviderInterface implementations (never real HTTP) so the
 * router's own selection/fallback/logging logic can be tested in
 * isolation from any provider's wire format — the providers themselves
 * are already covered by their own MockHandler-based tests (Phase 4/M2).
 */
class LLMRouterTest extends TestCase
{
    use RefreshDatabase;

    private function succeedingProvider(string $name): LLMProviderInterface
    {
        return new class($name) implements LLMProviderInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function chat(array $messages, array $options = []): LLMResponse
            {
                return LLMResponse::success("reply from {$this->name}", $this->name, 'model', 10, 5, 0.01, 42.0);
            }

            public function estimateCost(array $messages): float
            {
                return 0.01;
            }

            public function supports(LLMFeature $feature): bool
            {
                return true;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };
    }

    private function failingProvider(string $name): LLMProviderInterface
    {
        return new class($name) implements LLMProviderInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function chat(array $messages, array $options = []): LLMResponse
            {
                throw new LLMProviderRequestFailedException("{$this->name} is down");
            }

            public function estimateCost(array $messages): float
            {
                return 0.0;
            }

            public function supports(LLMFeature $feature): bool
            {
                return true;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };
    }

    /**
     * @param array<string, LLMProviderInterface> $providers
     * @param list<string> $fallbackChain
     * @param array<string, string> $tiers
     */
    private function makeRouter(
        array $providers,
        string $primaryProvider,
        array $fallbackChain,
        array $tiers,
        bool $enableFallback = true,
        bool $allowLocalToPaidFallback = false,
    ): LLMRouter {
        config(['nexus.platform.llm.provider_tiers' => $tiers]);
        config(['nexus.platform.llm.behavior.enable_fallback' => $enableFallback]);
        config(['nexus.platform.llm.behavior.allow_local_to_paid_fallback' => $allowLocalToPaidFallback]);

        $registry = new LLMProviderRegistry();
        foreach ($providers as $name => $provider) {
            $registry->register($name, $provider);
        }

        $settings = new LLMSettingsService(app(PlatformSettingRepositoryInterface::class), $registry);
        $settings->setFeatureProvider(LLMFeature::Reasoning, $primaryProvider);
        $settings->setFallbackChain($fallbackChain);

        return new LLMRouter(
            $registry,
            $settings,
            app(LLMUsageLogRepositoryInterface::class),
            app(MarginSettingsService::class),
            app(LLMBudgetGuard::class),
        );
    }

    public function test_route_primarySucceeds_returnsResponseAndLogsOneAttempt(): void
    {
        $router = $this->makeRouter(
            providers: ['openai' => $this->succeedingProvider('openai')],
            primaryProvider: 'openai',
            fallbackChain: [],
            tiers: ['openai' => 'paid'],
        );

        $response = $router->route(LLMFeature::Reasoning, [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('reply from openai', $response->content);
        $this->assertFalse($response->fromFallback);
        $this->assertDatabaseCount('nexus_llm_usage_logs', 1);
        $this->assertDatabaseHas('nexus_llm_usage_logs', ['provider' => 'openai', 'success' => 1, 'from_fallback' => 0]);
    }

    public function test_route_primaryFails_fallsBackAndSucceeds_marksFromFallbackTrueAndLogsBothAttempts(): void
    {
        $router = $this->makeRouter(
            providers: [
                'openai' => $this->failingProvider('openai'),
                'openrouter' => $this->succeedingProvider('openrouter'),
            ],
            primaryProvider: 'openai',
            fallbackChain: ['openrouter'],
            tiers: ['openai' => 'paid', 'openrouter' => 'free'],
        );

        $response = $router->route(LLMFeature::Reasoning, [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('reply from openrouter', $response->content);
        $this->assertTrue($response->fromFallback);
        $this->assertDatabaseCount('nexus_llm_usage_logs', 2);
        $this->assertDatabaseHas('nexus_llm_usage_logs', ['provider' => 'openai', 'success' => 0]);
        $this->assertDatabaseHas('nexus_llm_usage_logs', ['provider' => 'openrouter', 'success' => 1, 'from_fallback' => 1]);
    }

    public function test_route_entireChainFails_throwsAndLogsEveryAttempt(): void
    {
        $router = $this->makeRouter(
            providers: [
                'openai' => $this->failingProvider('openai'),
                'openrouter' => $this->failingProvider('openrouter'),
            ],
            primaryProvider: 'openai',
            fallbackChain: ['openrouter'],
            tiers: ['openai' => 'paid', 'openrouter' => 'free'],
        );

        try {
            $router->route(LLMFeature::Reasoning, [['role' => 'user', 'content' => 'hi']]);
            $this->fail('Expected AllLLMProvidersFailedException.');
        } catch (AllLLMProvidersFailedException $e) {
            // expected
        }

        $this->assertDatabaseCount('nexus_llm_usage_logs', 2);
    }

    public function test_route_localPrimary_neverAutoFallsBackToPaidCandidate(): void
    {
        $router = $this->makeRouter(
            providers: [
                'qwen-14b-local' => $this->failingProvider('qwen-14b-local'),
                'openai' => $this->succeedingProvider('openai'),
            ],
            primaryProvider: 'qwen-14b-local',
            fallbackChain: ['openai'],
            tiers: ['qwen-14b-local' => 'free', 'openai' => 'paid'],
            allowLocalToPaidFallback: false,
        );

        try {
            $router->route(LLMFeature::Reasoning, [['role' => 'user', 'content' => 'hi']]);
            $this->fail('Expected AllLLMProvidersFailedException — paid candidate must be skipped.');
        } catch (AllLLMProvidersFailedException $e) {
            // expected: openai was never attempted, so only 1 log row exists
        }

        $this->assertDatabaseCount('nexus_llm_usage_logs', 1);
        $this->assertDatabaseMissing('nexus_llm_usage_logs', ['provider' => 'openai']);
    }

    public function test_route_localPrimary_withAllowLocalToPaidFallbackTrue_permitsPaidFallback(): void
    {
        $router = $this->makeRouter(
            providers: [
                'qwen-14b-local' => $this->failingProvider('qwen-14b-local'),
                'openai' => $this->succeedingProvider('openai'),
            ],
            primaryProvider: 'qwen-14b-local',
            fallbackChain: ['openai'],
            tiers: ['qwen-14b-local' => 'free', 'openai' => 'paid'],
            allowLocalToPaidFallback: true,
        );

        $response = $router->route(LLMFeature::Reasoning, [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('reply from openai', $response->content);
        $this->assertTrue($response->fromFallback);
    }

    public function test_route_withFallbackDisabled_rethrowsPrimaryExceptionWithoutTryingChain(): void
    {
        $router = $this->makeRouter(
            providers: [
                'openai' => $this->failingProvider('openai'),
                'openrouter' => $this->succeedingProvider('openrouter'),
            ],
            primaryProvider: 'openai',
            fallbackChain: ['openrouter'],
            tiers: ['openai' => 'paid', 'openrouter' => 'free'],
            enableFallback: false,
        );

        $this->expectException(LLMProviderRequestFailedException::class);

        try {
            $router->route(LLMFeature::Reasoning, [['role' => 'user', 'content' => 'hi']]);
        } finally {
            $this->assertDatabaseCount('nexus_llm_usage_logs', 1);
            $this->assertDatabaseMissing('nexus_llm_usage_logs', ['provider' => 'openrouter']);
        }
    }
}
