<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\SimpleReasoningEngine;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPattern;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionPatternRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ReasoningType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * A pure, framework-free Unit test — `SimpleReasoningEngine` only depends
 * on `ExecutionPatternRepositoryInterface` (faked inline here), no Laravel
 * facade, the same shape `DeterministicPlannerTest` already establishes.
 */
class SimpleReasoningEngineTest extends TestCase
{
    public function test_think_withNoSimilarPatterns_usesTheDefaultConfidence(): void
    {
        $engine = new SimpleReasoningEngine($this->patterns([]));

        $trace = $engine->think($this->goal(), $this->profile(), tenantId: 1);

        $this->assertSame(ReasoningType::PreExecution, $trace->reasoningType);
        $this->assertSame(0.5, $trace->confidenceScore->value);
        $this->assertSame([], $trace->alternatives);
        $this->assertNull($trace->executionId());
        $this->assertStringContainsString('No similar past executions found', $trace->thoughts[0]);
    }

    public function test_think_withSimilarPatterns_derivesConfidenceFromTheirAverageSuccessRate(): void
    {
        $pattern1 = ExecutionPattern::create(1, 'sales|revenue', AgentType::Ceo, ['report.sales.generate'], new DateTimeImmutable());
        $pattern1->recordOutcome(true, ['report.sales.generate'], new DateTimeImmutable()); // 100% over 2 uses
        $pattern2 = ExecutionPattern::create(1, 'coupon', AgentType::Ceo, ['commerce.coupon.create'], new DateTimeImmutable()); // 100% over 1 use

        $engine = new SimpleReasoningEngine($this->patterns([$pattern1, $pattern2]));

        $trace = $engine->think($this->goal(), $this->profile(), tenantId: 1);

        $this->assertSame(1.0, $trace->confidenceScore->value);
        $this->assertStringContainsString('Found 2 similar past goal pattern(s)', $trace->thoughts[0]);
    }

    public function test_reflect_derivesConfidenceFromTheRealSuccessRate(): void
    {
        $engine = new SimpleReasoningEngine($this->patterns([]));
        $preReasoning = $engine->think($this->goal(), $this->profile(), tenantId: 1);

        $result = $this->completedResult();

        $trace = $engine->reflect($result, $preReasoning, tenantId: 1, executionId: 42);

        $this->assertSame(ReasoningType::PostExecution, $trace->reasoningType);
        $this->assertSame(42, $trace->executionId());
        $this->assertSame(1.0, $trace->confidenceScore->value);
        $this->assertStringContainsString('The planned approach worked', $trace->decision);
    }

    public function test_reflect_onAPartialResult_recommendsReviewingTheFailure(): void
    {
        $engine = new SimpleReasoningEngine($this->patterns([]));
        $preReasoning = $engine->think($this->goal(), $this->profile(), tenantId: 1);

        $succeeded = new ExecutionStep('report.sales.generate', []);
        $succeeded->markAsRunning();
        $succeeded->markAsCompleted(['total' => 100]);

        $failed = new ExecutionStep('commerce.coupon.create', []);
        $failed->markAsRunning();
        $failed->markAsFailed('Permission denied');

        $result = ExecutionResult::fromSteps($this->goal(), [$succeeded, $failed], 1.5);

        $trace = $engine->reflect($result, $preReasoning, tenantId: 1, executionId: 42);

        $this->assertSame(0.5, $trace->confidenceScore->value);
        $this->assertStringContainsString('did not fully succeed', $trace->decision);
        $this->assertStringContainsString('commerce.coupon.create', implode(' ', $trace->thoughts));
    }

    private function patterns(array $similar): ExecutionPatternRepositoryInterface
    {
        return new class($similar) implements ExecutionPatternRepositoryInterface {
            public function __construct(private readonly array $similar)
            {
            }

            public function save(ExecutionPattern $pattern): void
            {
            }

            public function findExisting(int $tenantId, string $goalPattern, AgentType $agentType): ?ExecutionPattern
            {
                return null;
            }

            public function findSimilarPatterns(int $tenantId, string $goal, AgentType $agentType, int $limit): array
            {
                return $this->similar;
            }
        };
    }

    private function completedResult(): ExecutionResult
    {
        $step = new ExecutionStep('report.sales.generate', []);
        $step->markAsRunning();
        $step->markAsCompleted(['total' => 100]);

        return ExecutionResult::fromSteps($this->goal(), [$step], 1.2);
    }

    private function goal(): Goal
    {
        return Goal::fromText('Increase sales by 15% this week', AgentType::Ceo);
    }

    private function profile(): AgentProfile
    {
        return AgentProfile::fromConfig(AgentType::Ceo, [
            'name' => 'CEO Agent',
            'description' => 'Test profile',
            'planning_rules' => ['default' => []],
            'default_inputs' => [],
            'permissions' => [],
        ]);
    }
}
