<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Llm\Application\Services\LLMSettingsService;
use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderNotFoundException;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Same hot-reload proof as MarginSettingsServiceTest (Phase 3/M5): a fresh
 * install with no admin-set rows falls back to config(), and set() is
 * visible to the very next get() with no cache-clear command run.
 */
class LLMSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_providerForFeature_withNoOverrideRow_fallsBackToConfig(): void
    {
        config(['nexus.platform.llm.feature_providers.reasoning' => 'qwen-14b-local']);

        $this->assertSame('qwen-14b-local', app(LLMSettingsService::class)->providerForFeature(LLMFeature::Reasoning));
    }

    public function test_setFeatureProvider_isImmediatelyVisibleToTheNextGet_withNoCacheClear(): void
    {
        config(['nexus.platform.llm.feature_providers.reasoning' => 'qwen-14b-local']);
        $service = app(LLMSettingsService::class);
        $this->assertSame('qwen-14b-local', $service->providerForFeature(LLMFeature::Reasoning));

        $service->setFeatureProvider(LLMFeature::Reasoning, 'openai');

        $this->assertSame('openai', $service->providerForFeature(LLMFeature::Reasoning));
        $this->assertDatabaseHas('nexus_platform_settings', ['key' => 'llm.feature_provider.reasoning', 'value' => 'openai']);
    }

    public function test_setFeatureProvider_isVisibleToAFreshServiceInstance(): void
    {
        app(LLMSettingsService::class)->setFeatureProvider(LLMFeature::Classification, 'groq');

        $fresh = app(LLMSettingsService::class);
        $this->assertSame('groq', $fresh->providerForFeature(LLMFeature::Classification));
    }

    public function test_setFeatureProvider_withUnknownProviderId_throwsAndDoesNotPersist(): void
    {
        $service = app(LLMSettingsService::class);

        $this->expectException(LLMProviderNotFoundException::class);

        try {
            $service->setFeatureProvider(LLMFeature::Reasoning, 'made-up-provider');
        } finally {
            $this->assertDatabaseMissing('nexus_platform_settings', ['key' => 'llm.feature_provider.reasoning']);
        }
    }

    public function test_fallbackChain_withNoOverrideRow_fallsBackToConfig(): void
    {
        config(['nexus.platform.llm.fallback_chain' => ['openrouter', 'groq']]);

        $this->assertSame(['openrouter', 'groq'], app(LLMSettingsService::class)->fallbackChain());
    }

    public function test_setFallbackChain_roundTripsOrderedList(): void
    {
        $service = app(LLMSettingsService::class);

        $service->setFallbackChain(['groq', 'openrouter', 'qwen-14b-local']);

        $this->assertSame(['groq', 'openrouter', 'qwen-14b-local'], $service->fallbackChain());
    }

    public function test_setFallbackChain_withAnyUnknownProviderId_throws(): void
    {
        $service = app(LLMSettingsService::class);

        $this->expectException(LLMProviderNotFoundException::class);

        $service->setFallbackChain(['openrouter', 'not-a-real-provider']);
    }

    public function test_costControl_withNoOverrideRow_fallsBackToConfig(): void
    {
        config([
            'nexus.platform.llm.cost_control.daily_budget_per_agent_irt' => 50000,
            'nexus.platform.llm.cost_control.monthly_budget_per_business_irt' => 1000000,
        ]);
        $service = app(LLMSettingsService::class);

        $this->assertSame(50000, $service->dailyBudgetPerAgentIrt());
        $this->assertSame(1000000, $service->monthlyBudgetPerBusinessIrt());
    }

    public function test_setCostControl_overridesBothThresholds(): void
    {
        $service = app(LLMSettingsService::class);

        $service->setCostControl(75000, 1500000);

        $this->assertSame(75000, $service->dailyBudgetPerAgentIrt());
        $this->assertSame(1500000, $service->monthlyBudgetPerBusinessIrt());
    }
}
