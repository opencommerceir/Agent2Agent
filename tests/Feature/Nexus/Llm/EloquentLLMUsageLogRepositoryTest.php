<?php

namespace Tests\Feature\Nexus\Llm;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Llm\Domain\Entities\LLMUsageLog;
use App\Domains\Nexus\Llm\Domain\Repositories\LLMUsageLogRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentLLMUsageLogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_thenFindByBusinessId_roundTripsAllFields(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);

        $log = LLMUsageLog::record(
            businessId: $business->id,
            agentId: 7,
            feature: 'negotiation',
            provider: 'openai',
            model: 'gpt-4o',
            promptTokens: 200,
            completionTokens: 80,
            realCostUsd: 0.002,
            chargedCostUsd: 0.0026,
            latencyMs: 340,
            fromFallback: false,
            success: true,
        );

        $saved = app(LLMUsageLogRepositoryInterface::class)->save($log);

        $this->assertNotNull($saved->id());
        $this->assertDatabaseHas('nexus_llm_usage_logs', [
            'business_id' => $business->id,
            'agent_id' => 7,
            'feature' => 'negotiation',
            'provider' => 'openai',
            'prompt_tokens' => 200,
            'completion_tokens' => 80,
            'total_tokens' => 280,
            'success' => 1,
        ]);

        $found = app(LLMUsageLogRepositoryInterface::class)->findByBusinessId($business->id);

        $this->assertCount(1, $found);
        $this->assertSame('gpt-4o', $found[0]->model());
        $this->assertEqualsWithDelta(0.0006, $found[0]->marginUsd(), 0.0000001);
    }

    public function test_save_withNullBusinessAndAgentId_persistsAsAdminTestConnection(): void
    {
        $log = LLMUsageLog::record(
            businessId: null,
            agentId: null,
            feature: 'admin_test_connection',
            provider: 'groq',
            model: 'llama-3.1-8b-instant',
            promptTokens: 3,
            completionTokens: 1,
            realCostUsd: 0.0,
            chargedCostUsd: 0.0,
            latencyMs: 90,
            fromFallback: false,
            success: true,
        );

        app(LLMUsageLogRepositoryInterface::class)->save($log);

        $this->assertDatabaseHas('nexus_llm_usage_logs', [
            'business_id' => null,
            'agent_id' => null,
            'feature' => 'admin_test_connection',
        ]);
    }

    public function test_findByBusinessId_excludesOtherBusinesses(): void
    {
        $businessA = app(RegisterBusinessAction::class)->execute('الف', 'A', BusinessType::Company, Industry::Technology);
        $businessB = app(RegisterBusinessAction::class)->execute('ب', 'B', BusinessType::Company, Industry::Technology);

        app(LLMUsageLogRepositoryInterface::class)->save(LLMUsageLog::record(
            $businessA->id, null, 'reasoning', 'openai', 'gpt-4o', 10, 5, 0.0, 0.0, 100, false, true,
        ));
        app(LLMUsageLogRepositoryInterface::class)->save(LLMUsageLog::record(
            $businessB->id, null, 'reasoning', 'openai', 'gpt-4o', 10, 5, 0.0, 0.0, 100, false, true,
        ));

        $found = app(LLMUsageLogRepositoryInterface::class)->findByBusinessId($businessA->id);

        $this->assertCount(1, $found);
        $this->assertSame($businessA->id, $found[0]->businessId());
    }
}
