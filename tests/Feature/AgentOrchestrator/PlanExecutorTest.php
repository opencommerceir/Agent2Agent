<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Core\Application\DTOs\AuthContext;
use App\Modules\AgentOrchestrator\Application\Services\PlanExecutor;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Events\StepExecuted;
use App\Modules\AgentOrchestrator\Domain\Services\ToolInvokerInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\StepStatus;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * A Laravel-booted Feature test, not a framework-free Unit test, purely
 * because PlanExecutor dispatches real Domain Events through the `Event`
 * facade — the same reason several other "service-level" tests in this
 * codebase are Feature tests despite testing one class in isolation (see
 * MCPRateLimitTest's own docblock). No database is touched — a fake
 * ToolInvokerInterface stands in for real capability execution.
 */
class PlanExecutorTest extends TestCase
{
    public function test_execute_runsEveryStepInOrderAndMarksThemCompleted(): void
    {
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);
        $stepOne = new ExecutionStep('report.sales.generate', ['start_date' => '2026-01-01', 'end_date' => '2026-01-07']);
        $stepTwo = new ExecutionStep('commerce.coupon.create', ['code' => 'COUPON-ABCDE']);
        $plan = new ExecutionPlan($goal, [$stepOne, $stepTwo]);

        $invoked = [];
        $toolInvoker = $this->fakeInvoker(function (string $capability) use (&$invoked) {
            $invoked[] = $capability;

            return ['ok' => true];
        });

        $executor = new PlanExecutor($toolInvoker);
        $result = $executor->execute($plan, $this->authContext());

        $this->assertSame(['report.sales.generate', 'commerce.coupon.create'], $invoked);
        $this->assertSame('completed', $result->status);
        $this->assertSame(StepStatus::Completed, $result->steps[0]->status());
        $this->assertSame(['ok' => true], $result->steps[0]->output());
    }

    public function test_execute_continuesPastAFailedStep(): void
    {
        $goal = Goal::fromText('Increase sales', AgentType::Ceo);
        $failing = new ExecutionStep('commerce.coupon.create', ['code' => 'COUPON-ABCDE']);
        $succeeding = new ExecutionStep('notification.message.send', []);
        $plan = new ExecutionPlan($goal, [$failing, $succeeding]);

        $invoked = [];
        $toolInvoker = $this->fakeInvoker(function (string $capability) use (&$invoked) {
            $invoked[] = $capability;

            if ($capability === 'commerce.coupon.create') {
                throw new RuntimeException('Coupon code already taken.');
            }

            return ['sent' => true];
        });

        $executor = new PlanExecutor($toolInvoker);
        $result = $executor->execute($plan, $this->authContext());

        $this->assertSame(['commerce.coupon.create', 'notification.message.send'], $invoked);
        $this->assertSame('partial', $result->status);
        $this->assertSame(StepStatus::Failed, $result->steps[0]->status());
        $this->assertSame('Coupon code already taken.', $result->steps[0]->errorMessage());
        $this->assertSame(StepStatus::Completed, $result->steps[1]->status());
    }

    public function test_execute_dispatchesStepExecutedForEveryTransition(): void
    {
        Event::fake([StepExecuted::class]);

        $goal = Goal::fromText('Increase sales', AgentType::Ceo);
        $step = new ExecutionStep('commerce.coupon.create', []);
        $plan = new ExecutionPlan($goal, [$step]);

        $executor = new PlanExecutor($this->fakeInvoker(fn () => ['ok' => true]));
        $executor->execute($plan, $this->authContext());

        Event::assertDispatched(StepExecuted::class, fn (StepExecuted $event) => $event->phase === 'started');
        Event::assertDispatched(StepExecuted::class, fn (StepExecuted $event) => $event->phase === 'completed');
    }

    private function fakeInvoker(\Closure $invoke): ToolInvokerInterface
    {
        return new class($invoke) implements ToolInvokerInterface {
            public function __construct(private readonly \Closure $invoke)
            {
            }

            public function invoke(string $capability, array $input, AuthContext $context): array
            {
                return ($this->invoke)($capability, $input, $context);
            }
        };
    }

    private function authContext(): AuthContext
    {
        return new AuthContext(tenantId: 1, agentId: 1);
    }
}
