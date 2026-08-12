<?php

namespace Tests\Unit\Nexus\Llm;

use App\Domains\Nexus\Llm\Domain\Entities\LLMUsageLog;
use PHPUnit\Framework\TestCase;

class LLMUsageLogTest extends TestCase
{
    public function test_record_capturesAllFieldsAndSumsTotalTokens(): void
    {
        $log = LLMUsageLog::record(
            businessId: 1,
            agentId: 2,
            feature: 'negotiation',
            provider: 'openai',
            model: 'gpt-4o',
            promptTokens: 100,
            completionTokens: 50,
            realCostUsd: 0.001,
            chargedCostUsd: 0.0013,
            latencyMs: 250,
            fromFallback: false,
            success: true,
        );

        $this->assertNull($log->id());
        $this->assertSame(1, $log->businessId());
        $this->assertSame(2, $log->agentId());
        $this->assertSame('negotiation', $log->feature());
        $this->assertSame('openai', $log->provider());
        $this->assertSame('gpt-4o', $log->model());
        $this->assertSame(100, $log->promptTokens());
        $this->assertSame(50, $log->completionTokens());
        $this->assertSame(150, $log->totalTokens());
        $this->assertSame(0.001, $log->realCostUsd());
        $this->assertSame(0.0013, $log->chargedCostUsd());
        $this->assertFalse($log->fromFallback());
        $this->assertTrue($log->success());
        $this->assertNull($log->errorMessage());
    }

    public function test_record_computesMarginAsChargedMinusReal(): void
    {
        $log = LLMUsageLog::record(
            businessId: 1,
            agentId: null,
            feature: 'reasoning',
            provider: 'claude',
            model: 'claude-3-opus-20240229',
            promptTokens: 10,
            completionTokens: 5,
            realCostUsd: 0.10,
            chargedCostUsd: 0.13,
            latencyMs: 500,
            fromFallback: false,
            success: true,
        );

        $this->assertEqualsWithDelta(0.03, $log->marginUsd(), 0.0000001);
    }

    public function test_record_forAdminTestConnection_allowsNullBusinessAndAgent(): void
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
            latencyMs: 80,
            fromFallback: false,
            success: true,
        );

        $this->assertNull($log->businessId());
        $this->assertNull($log->agentId());
        $this->assertSame('admin_test_connection', $log->feature());
    }

    public function test_record_forFailedAttempt_capturesErrorMessage(): void
    {
        $log = LLMUsageLog::record(
            businessId: 1,
            agentId: 2,
            feature: 'classification',
            provider: 'qwen-14b-local',
            model: 'qwen2.5:14b',
            promptTokens: 0,
            completionTokens: 0,
            realCostUsd: 0.0,
            chargedCostUsd: 0.0,
            latencyMs: 30000,
            fromFallback: false,
            success: false,
            errorMessage: 'Connection refused',
        );

        $this->assertFalse($log->success());
        $this->assertSame('Connection refused', $log->errorMessage());
    }
}
