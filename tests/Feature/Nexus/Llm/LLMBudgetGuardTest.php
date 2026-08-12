<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Llm\Application\Services\LLMBudgetGuard;
use App\Domains\Nexus\Llm\Domain\Entities\LLMUsageLog;
use App\Domains\Nexus\Llm\Domain\Exceptions\BudgetLimitExceededException;
use App\Domains\Nexus\Llm\Domain\Repositories\LLMUsageLogRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LLMBudgetGuardTest extends TestCase
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
        ]);
    }

    private function seedUsage(?int $businessId, ?int $agentId, float $chargedCostUsd): void
    {
        app(LLMUsageLogRepositoryInterface::class)->save(LLMUsageLog::record(
            businessId: $businessId,
            agentId: $agentId,
            feature: 'reasoning',
            provider: 'openai',
            model: 'gpt-4o',
            promptTokens: 100,
            completionTokens: 50,
            realCostUsd: $chargedCostUsd,
            chargedCostUsd: $chargedCostUsd,
            latencyMs: 100,
            fromFallback: false,
            success: true,
        ));
    }

    public function test_assertWithinBudget_forFreeProvider_neverChecksEvenWithMassiveCost(): void
    {
        $this->expectNotToPerformAssertions();

        app(LLMBudgetGuard::class)->assertWithinBudget(agentId: 1, businessId: 1, providerId: 'qwen-14b-local', estimatedCostUsd: 99999.0);
    }

    public function test_assertWithinBudget_withNullAgentAndBusiness_neverChecks(): void
    {
        $this->expectNotToPerformAssertions();

        app(LLMBudgetGuard::class)->assertWithinBudget(agentId: null, businessId: null, providerId: 'openai', estimatedCostUsd: 99999.0);
    }

    public function test_assertWithinBudget_forPaidProvider_underBudget_doesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();

        app(LLMBudgetGuard::class)->assertWithinBudget(agentId: 5, businessId: null, providerId: 'openai', estimatedCostUsd: 0.01);
    }

    public function test_assertWithinBudget_forPaidProvider_overDailyAgentBudget_throws(): void
    {
        // 100000 IRT / 600000 IRT-per-USD ≈ 0.1667 USD budget; seed spend
        // right up against it so one more small call tips it over.
        $this->seedUsage(businessId: null, agentId: 5, chargedCostUsd: 0.16);

        $this->expectException(BudgetLimitExceededException::class);

        app(LLMBudgetGuard::class)->assertWithinBudget(agentId: 5, businessId: null, providerId: 'openai', estimatedCostUsd: 0.05);
    }

    public function test_assertWithinBudget_forPaidProvider_overMonthlyBusinessBudget_throws(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        $this->seedUsage(businessId: $business->id, agentId: null, chargedCostUsd: 1.6);

        $this->expectException(BudgetLimitExceededException::class);

        app(LLMBudgetGuard::class)->assertWithinBudget(agentId: null, businessId: $business->id, providerId: 'openai', estimatedCostUsd: 0.1);
    }

    public function test_assertWithinBudget_withZeroBudgetConfigured_treatsAsUnlimited(): void
    {
        config(['nexus.platform.llm.cost_control.daily_budget_per_agent_irt' => 0]);
        $this->seedUsage(businessId: null, agentId: 9, chargedCostUsd: 100.0);

        $this->expectNotToPerformAssertions();

        app(LLMBudgetGuard::class)->assertWithinBudget(agentId: 9, businessId: null, providerId: 'openai', estimatedCostUsd: 1.0);
    }
}
