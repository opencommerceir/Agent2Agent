<?php

namespace Tests\Unit\AgentOrchestrator;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\Priority;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\StepStatus;
use LogicException;
use PHPUnit\Framework\TestCase;

class ExecutionStepTest extends TestCase
{
    public function test_startsAsPending(): void
    {
        $step = new ExecutionStep('commerce.coupon.create', ['code' => 'COUPON-ABCDE']);

        $this->assertSame(StepStatus::Pending, $step->status());
        $this->assertSame(Priority::Medium, $step->priority);
        $this->assertNull($step->output());
        $this->assertNull($step->errorMessage());
    }

    public function test_markAsCompleted_recordsOutputAndStatus(): void
    {
        $step = new ExecutionStep('commerce.coupon.create', []);

        $step->markAsRunning();
        $step->markAsCompleted(['coupon' => ['id' => 1]]);

        $this->assertSame(StepStatus::Completed, $step->status());
        $this->assertSame(['coupon' => ['id' => 1]], $step->output());
        $this->assertNull($step->errorMessage());
    }

    public function test_markAsFailed_recordsErrorAndStatus(): void
    {
        $step = new ExecutionStep('commerce.coupon.create', []);

        $step->markAsRunning();
        $step->markAsFailed('Coupon code already taken');

        $this->assertSame(StepStatus::Failed, $step->status());
        $this->assertSame('Coupon code already taken', $step->errorMessage());
        $this->assertNull($step->output());
    }

    public function test_markAsRunning_rejectsANonPendingStep(): void
    {
        $step = new ExecutionStep('commerce.coupon.create', []);
        $step->markAsRunning();

        $this->expectException(LogicException::class);

        $step->markAsRunning();
    }

    public function test_reconstruct_rebuildsATerminalStepDirectly(): void
    {
        $step = ExecutionStep::reconstruct(
            capability: 'report.sales.generate',
            input: ['start_date' => '2026-01-01', 'end_date' => '2026-01-07'],
            priority: Priority::High,
            status: StepStatus::Completed,
            output: ['report' => ['totalSales' => 1000]],
            errorMessage: null,
        );

        $this->assertSame(StepStatus::Completed, $step->status());
        $this->assertSame(['report' => ['totalSales' => 1000]], $step->output());
    }
}
