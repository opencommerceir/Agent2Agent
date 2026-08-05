<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AlternativePlan;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ConfidenceScore;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ReasoningType;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

class ReasoningTraceTest extends TestCase
{
    public function test_create_startsWithNoIdAndNoExecutionIdByDefault(): void
    {
        $trace = $this->trace();

        $this->assertNull($trace->id());
        $this->assertNull($trace->executionId());
        $this->assertSame(ReasoningType::PreExecution, $trace->reasoningType);
    }

    public function test_create_canBeGivenAnExecutionIdUpfront(): void
    {
        $trace = ReasoningTrace::create(
            tenantId: 1,
            agentType: AgentType::Ceo,
            goalText: 'Increase sales by 15% this week',
            reasoningType: ReasoningType::PostExecution,
            thoughts: ['Execution finished with status [completed], 100% success rate.'],
            alternatives: [],
            confidenceScore: ConfidenceScore::fromFloat(1.0),
            decision: 'The planned approach worked.',
            explanation: 'Every step succeeded.',
            executionId: 42,
        );

        $this->assertSame(42, $trace->executionId());
    }

    public function test_assignId_isOneTimeOnly(): void
    {
        $trace = $this->trace();
        $trace->assignId(7);

        $this->assertSame(7, $trace->id());

        $this->expectException(LogicException::class);
        $trace->assignId(8);
    }

    public function test_assignExecutionId_isOneTimeOnly(): void
    {
        $trace = $this->trace();
        $trace->assignExecutionId(42);

        $this->assertSame(42, $trace->executionId());

        $this->expectException(LogicException::class);
        $trace->assignExecutionId(43);
    }

    public function test_reconstruct_rebuildsAnAlreadyPersistedTraceDirectly(): void
    {
        $alternative = AlternativePlan::create('Broad discount', 0.5, 'Higher volume but lower margins');

        $trace = ReasoningTrace::reconstruct(
            id: 9,
            tenantId: 1,
            agentType: AgentType::Ceo,
            goalText: 'Increase sales by 15% this week',
            reasoningType: ReasoningType::PreExecution,
            thoughts: ['Found 2 similar past goal pattern(s).'],
            alternatives: [$alternative],
            confidenceScore: ConfidenceScore::fromFloat(0.85),
            decision: 'Use a targeted coupon campaign.',
            explanation: 'Based on past success.',
            executionId: 42,
            createdAt: new DateTimeImmutable('2026-08-14 00:00:00'),
        );

        $this->assertSame(9, $trace->id());
        $this->assertSame(42, $trace->executionId());
        $this->assertSame([$alternative], $trace->alternatives);
    }

    private function trace(): ReasoningTrace
    {
        return ReasoningTrace::create(
            tenantId: 1,
            agentType: AgentType::Ceo,
            goalText: 'Increase sales by 15% this week',
            reasoningType: ReasoningType::PreExecution,
            thoughts: ['No similar past executions found.'],
            alternatives: [],
            confidenceScore: ConfidenceScore::fromFloat(0.5),
            decision: 'Proceed with the ceo persona\'s planned capability sequence.',
            explanation: 'Deterministic reasoning.',
        );
    }
}
