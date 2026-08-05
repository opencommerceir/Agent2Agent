<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Application\Services\ResultAggregator;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ResultAggregatorTest extends TestCase
{
    private ResultAggregator $aggregator;

    protected function setUp(): void
    {
        $this->aggregator = new ResultAggregator();
    }

    public function test_aggregate_mergesStepsAndSumsDurationFromEveryResult(): void
    {
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);

        $stepOne = new ExecutionStep('report.sales.generate', []);
        $stepOne->markAsRunning();
        $stepOne->markAsCompleted(['report' => []]);
        $resultOne = ExecutionResult::fromSteps($goal, [$stepOne], 1.0);

        $stepTwo = new ExecutionStep('commerce.coupon.create', []);
        $stepTwo->markAsRunning();
        $stepTwo->markAsCompleted(['coupon' => []]);
        $resultTwo = ExecutionResult::fromSteps($goal, [$stepTwo], 2.5);

        $aggregated = $this->aggregator->aggregate([$resultOne, $resultTwo]);

        $this->assertCount(2, $aggregated->steps);
        $this->assertSame(['report.sales.generate', 'commerce.coupon.create'], array_map(fn ($s) => $s->capability, $aggregated->steps));
        $this->assertSame(3.5, $aggregated->executionTimeSeconds);
        $this->assertSame('completed', $aggregated->status);
    }

    public function test_aggregate_rejectsAnEmptyList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->aggregator->aggregate([]);
    }

    public function test_resolveConflicts_picksTheHighestSuccessRate(): void
    {
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);

        $failedStep = new ExecutionStep('commerce.coupon.create', []);
        $failedStep->markAsRunning();
        $failedStep->markAsFailed('boom');
        $worse = ExecutionResult::fromSteps($goal, [$failedStep], 1.0);

        $completedStep = new ExecutionStep('commerce.coupon.create', []);
        $completedStep->markAsRunning();
        $completedStep->markAsCompleted(['coupon' => []]);
        $better = ExecutionResult::fromSteps($goal, [$completedStep], 1.0);

        $this->assertSame($better, $this->aggregator->resolveConflicts([$worse, $better]));
        $this->assertSame($better, $this->aggregator->resolveConflicts([$better, $worse]));
    }

    public function test_resolveConflicts_rejectsAnEmptyList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->aggregator->resolveConflicts([]);
    }
}
