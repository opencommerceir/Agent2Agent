<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\ExplanationGenerator;
use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AlternativePlan;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ConfidenceScore;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ReasoningType;
use PHPUnit\Framework\TestCase;

class ExplanationGeneratorTest extends TestCase
{
    public function test_generate_includesGoalAgentDecisionAndConfidence(): void
    {
        $trace = ReasoningTrace::create(
            tenantId: 1,
            agentType: AgentType::Ceo,
            goalText: 'Increase sales by 15% this week',
            reasoningType: ReasoningType::PreExecution,
            thoughts: ['Goal requires a 15% increase.', 'Similar goal last month succeeded.'],
            alternatives: [],
            confidenceScore: ConfidenceScore::fromFloat(0.85),
            decision: 'Use a targeted coupon campaign.',
            explanation: 'Based on past success and current inventory.',
        );

        $explanation = (new ExplanationGenerator())->generate($trace);

        $this->assertStringContainsString('Increase sales by 15% this week', $explanation);
        $this->assertStringContainsString('ceo', $explanation);
        $this->assertStringContainsString('Use a targeted coupon campaign.', $explanation);
        $this->assertStringContainsString('85.0%', $explanation);
        $this->assertStringContainsString('1. Goal requires a 15% increase.', $explanation);
        $this->assertStringContainsString('2. Similar goal last month succeeded.', $explanation);
    }

    public function test_generate_listsAlternativesWhenPresent(): void
    {
        $trace = ReasoningTrace::create(
            tenantId: 1,
            agentType: AgentType::Ceo,
            goalText: 'Increase sales',
            reasoningType: ReasoningType::PreExecution,
            thoughts: ['Thinking...'],
            alternatives: [AlternativePlan::create('Focus on high-margin products', 0.7, 'Lower volume but higher profit')],
            confidenceScore: ConfidenceScore::fromFloat(0.85),
            decision: 'Use a targeted coupon campaign.',
            explanation: 'Based on past success.',
        );

        $explanation = (new ExplanationGenerator())->generate($trace);

        $this->assertStringContainsString('Alternatives Considered', $explanation);
        $this->assertStringContainsString('Focus on high-margin products', $explanation);
        $this->assertStringContainsString('70.0%', $explanation);
    }

    public function test_generate_omitsAlternativesSectionWhenNone(): void
    {
        $trace = ReasoningTrace::create(
            tenantId: 1,
            agentType: AgentType::Ceo,
            goalText: 'Increase sales',
            reasoningType: ReasoningType::PostExecution,
            thoughts: ['Execution finished with status [completed].'],
            alternatives: [],
            confidenceScore: ConfidenceScore::fromFloat(1.0),
            decision: 'The planned approach worked.',
            explanation: 'Every step succeeded.',
            executionId: 42,
        );

        $explanation = (new ExplanationGenerator())->generate($trace);

        $this->assertStringNotContainsString('Alternatives Considered', $explanation);
    }
}
