<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use PHPUnit\Framework\TestCase;

class ExecutionResultTest extends TestCase
{
    public function test_fromSteps_statusIsCompletedWhenNoStepFailed(): void
    {
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);
        $step = new ExecutionStep('commerce.coupon.create', []);
        $step->markAsRunning();
        $step->markAsCompleted(['coupon' => []]);

        $result = ExecutionResult::fromSteps($goal, [$step], 1.5);

        $this->assertSame('completed', $result->status);
        $this->assertStringContainsString('1 of 1 step(s) completed', $result->summary);
    }

    public function test_fromSteps_statusIsPartialWhenSomeStepsFail(): void
    {
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);

        $completed = new ExecutionStep('report.sales.generate', []);
        $completed->markAsRunning();
        $completed->markAsCompleted(['report' => []]);

        $failed = new ExecutionStep('commerce.coupon.create', []);
        $failed->markAsRunning();
        $failed->markAsFailed('Coupon code already taken');

        $result = ExecutionResult::fromSteps($goal, [$completed, $failed], 2.0);

        $this->assertSame('partial', $result->status);
        $this->assertStringContainsString('1 of 2 step(s) completed', $result->summary);
        $this->assertStringContainsString('1 failed', $result->summary);
    }

    public function test_fromSteps_statusIsFailedWhenEveryStepFails(): void
    {
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);

        $failed = new ExecutionStep('commerce.coupon.create', []);
        $failed->markAsRunning();
        $failed->markAsFailed('boom');

        $result = ExecutionResult::fromSteps($goal, [$failed], 0.5);

        $this->assertSame('failed', $result->status);
    }

    public function test_fromSteps_statusIsEmptyWhenNoStepsPlanned(): void
    {
        $goal = Goal::fromText('Do something unrecognized', AgentType::Ceo);

        $result = ExecutionResult::fromSteps($goal, [], 0.01);

        $this->assertSame('empty', $result->status);
        $this->assertSame('No matching plan was found for this goal.', $result->summary);
    }

    public function test_isSuccessful_isTrueOnlyWhenEveryStepCompleted(): void
    {
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);

        $completed = new ExecutionStep('commerce.coupon.create', []);
        $completed->markAsRunning();
        $completed->markAsCompleted(['coupon' => []]);

        $this->assertTrue(ExecutionResult::fromSteps($goal, [$completed], 1.0)->isSuccessful());

        $failed = new ExecutionStep('report.sales.generate', []);
        $failed->markAsRunning();
        $failed->markAsFailed('boom');

        $this->assertFalse(ExecutionResult::fromSteps($goal, [$completed, $failed], 1.0)->isSuccessful());
        $this->assertFalse(ExecutionResult::fromSteps($goal, [], 0.01)->isSuccessful());
    }

    public function test_successfulAndFailedCapabilities_splitStepsByTerminalStatus(): void
    {
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);

        $completed = new ExecutionStep('report.sales.generate', []);
        $completed->markAsRunning();
        $completed->markAsCompleted(['report' => []]);

        $failed = new ExecutionStep('commerce.coupon.create', []);
        $failed->markAsRunning();
        $failed->markAsFailed('boom');

        $result = ExecutionResult::fromSteps($goal, [$completed, $failed], 2.0);

        $this->assertSame(['report.sales.generate'], $result->successfulCapabilities());
        $this->assertSame(['commerce.coupon.create'], $result->failedCapabilities());
    }
}
