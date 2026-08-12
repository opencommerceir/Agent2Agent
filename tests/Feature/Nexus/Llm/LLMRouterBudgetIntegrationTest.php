<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Admin\Application\Services\MarginSettingsService;
use App\Domains\Nexus\Admin\Domain\Repositories\PlatformSettingRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Llm\Application\Services\LLMBudgetGuard;
use App\Domains\Nexus\Llm\Application\Services\LLMProviderRegistry;
use App\Domains\Nexus\Llm\Application\Services\LLMRouter;
use App\Domains\Nexus\Llm\Application\Services\LLMSettingsService;
use App\Domains\Nexus\Llm\Domain\Entities\LLMUsageLog;
use App\Domains\Nexus\Llm\Domain\Repositories\LLMUsageLogRepositoryInterface;
use App\Domains\Nexus\Llm\Domain\Services\LLMProviderInterface;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LLMRouterBudgetIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'nexus.platform.llm.provider_tiers' => ['openai' => 'paid', 'qwen-14b-local' => 'free'],
            'nexus.platform.llm.cost_control.usd_to_irt_rate' => 600000,
            'nexus.platform.llm.cost_control.daily_budget_per_agent_irt' => 100000,
            'nexus.platform.llm.cost_control.monthly_budget_per_business_irt' => 1000000,
            'nexus.platform.llm.behavior.enable_fallback' => true,
            'nexus.platform.llm.behavior.allow_local_to_paid_fallback' => false,
        ]);
    }

    private function fakeProvider(string $name, bool $succeeds = true): LLMProviderInterface
    {
        return new class($name, $succeeds) implements LLMProviderInterface {
            public function __construct(private readonly string $name, private readonly bool $succeeds)
            {
            }

            public function chat(array $messages, array $options = []): LLMResponse
            {
                return LLMResponse::success("reply from {$this->name}", $this->name, 'model', 10, 5, 0.05, 20.0);
            }

            public function estimateCost(array $messages): float
            {
                return 0.05;
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

    private function makeRouter(): LLMRouter
    {
        $registry = new LLMProviderRegistry();
        $registry->register('openai', $this->fakeProvider('openai'));
        $registry->register('qwen-14b-local', $this->fakeProvider('qwen-14b-local'));

        $settings = new LLMSettingsService(app(PlatformSettingRepositoryInterface::class), $registry);
        $settings->setFeatureProvider(LLMFeature::Reasoning, 'openai');
        $settings->setFallbackChain(['qwen-14b-local']);

        return new LLMRouter(
            $registry,
            $settings,
            app(LLMUsageLogRepositoryInterface::class),
            app(MarginSettingsService::class),
            app(LLMBudgetGuard::class),
        );
    }

    private function seedAgentUsage(int $agentId, float $chargedCostUsd): void
    {
        app(LLMUsageLogRepositoryInterface::class)->save(LLMUsageLog::record(
            null, $agentId, 'reasoning', 'openai', 'gpt-4o', 100, 50, $chargedCostUsd, $chargedCostUsd, 100, false, true,
        ));
    }

    private function seedBusinessUsage(int $businessId, float $chargedCostUsd): void
    {
        app(LLMUsageLogRepositoryInterface::class)->save(LLMUsageLog::record(
            $businessId, null, 'reasoning', 'openai', 'gpt-4o', 100, 50, $chargedCostUsd, $chargedCostUsd, 100, false, true,
        ));
    }

    public function test_route_agentOverDailyBudget_transparentlyFallsThroughToFreeProvider(): void
    {
        $this->seedAgentUsage(agentId: 5, chargedCostUsd: 0.16);
        $router = $this->makeRouter();

        $response = $router->route(LLMFeature::Reasoning, [['role' => 'user', 'content' => 'hi']], agentId: 5);

        $this->assertSame('reply from qwen-14b-local', $response->content);
        $this->assertTrue($response->fromFallback);
    }

    public function test_route_businessOverMonthlyBudget_stillSucceedsViaLocalProvider(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        $this->seedBusinessUsage($business->id, chargedCostUsd: 1.65);
        $router = $this->makeRouter();

        $response = $router->route(LLMFeature::Reasoning, [['role' => 'user', 'content' => 'hi']], businessId: $business->id);

        $this->assertSame('reply from qwen-14b-local', $response->content);
        $this->assertTrue($response->fromFallback);
    }

    public function test_route_withNullAgentAndBusiness_isNeverBudgetChecked(): void
    {
        // No agentId/businessId passed at all — same shape an admin
        // "test connection" call would use — so even an absurdly high
        // prior spend (if it existed) could never block this call.
        $router = $this->makeRouter();

        $response = $router->route(LLMFeature::Reasoning, [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('reply from openai', $response->content);
        $this->assertFalse($response->fromFallback);
    }
}
