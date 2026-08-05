<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Events\GoalCompleted;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * `LearnFromExecutionListener` reacting to a real, dispatched
 * `GoalCompleted` event (Phase 6, Stage 4, §7.29) — a Laravel-booted
 * Feature test since it exercises the real `Event::listen()` registration
 * from `AgentOrchestratorServiceProvider::boot()` and writes to the real
 * `execution_patterns` table, the same reason `PlanExecutorTest`'s own
 * event-dispatching tests are Feature, not Unit.
 */
class LearnFromExecutionListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_aSuccessfulRunCreatesANewPattern(): void
    {
        $tenantId = $this->tenant();

        Event::dispatch(new GoalCompleted($this->successfulResult('Increase sales this week'), $tenantId, 1));

        $this->assertDatabaseHas('execution_patterns', [
            'tenant_id' => $tenantId,
            'goal_pattern' => 'sales',
            'agent_type' => 'ceo',
            'usage_count' => 1,
            'success_rate' => 1.0,
        ]);
    }

    public function test_aSecondSuccessfulOccurrenceReinforcesTheSameRowRatherThanInsertingASecondOne(): void
    {
        $tenantId = $this->tenant();

        Event::dispatch(new GoalCompleted($this->successfulResult('Increase sales this week'), $tenantId, 1));
        Event::dispatch(new GoalCompleted($this->successfulResult('Boost sales this month'), $tenantId, 1));

        $this->assertDatabaseCount('execution_patterns', 1);
        $this->assertDatabaseHas('execution_patterns', [
            'tenant_id' => $tenantId,
            'goal_pattern' => 'sales',
            'usage_count' => 2,
            'success_rate' => 1.0,
        ]);
    }

    public function test_aFailureAgainstAnAlreadyLearnedPatternDegradesItsSuccessRate(): void
    {
        $tenantId = $this->tenant();

        Event::dispatch(new GoalCompleted($this->successfulResult('Increase sales this week'), $tenantId, 1));
        Event::dispatch(new GoalCompleted($this->failedResult('Boost sales this month'), $tenantId, 1));

        $this->assertDatabaseCount('execution_patterns', 1);
        $this->assertDatabaseHas('execution_patterns', [
            'tenant_id' => $tenantId,
            'goal_pattern' => 'sales',
            'usage_count' => 2,
            'success_rate' => 0.5,
        ]);
    }

    public function test_aFirstTimeFailureWithNoExistingPatternCreatesNothing(): void
    {
        $tenantId = $this->tenant();

        Event::dispatch(new GoalCompleted($this->failedResult('Boost sales this month'), $tenantId, 1));

        $this->assertDatabaseCount('execution_patterns', 0);
    }

    public function test_aGoalWithNoRecognizedKeywordIsNeverLearned(): void
    {
        $tenantId = $this->tenant();

        Event::dispatch(new GoalCompleted($this->successfulResult('Water the plants'), $tenantId, 1));

        $this->assertDatabaseCount('execution_patterns', 0);
    }

    public function test_learningIsScopedPerTenant(): void
    {
        $tenantA = $this->tenant();
        $tenantB = $this->tenant();

        Event::dispatch(new GoalCompleted($this->successfulResult('Increase sales this week'), $tenantA, 1));

        $this->assertDatabaseCount('execution_patterns', 1);
        $this->assertDatabaseHas('execution_patterns', ['tenant_id' => $tenantA, 'goal_pattern' => 'sales']);
        $this->assertDatabaseMissing('execution_patterns', ['tenant_id' => $tenantB, 'goal_pattern' => 'sales']);
    }

    private function successfulResult(string $goalText): ExecutionResult
    {
        $goal = Goal::fromText($goalText, AgentType::Ceo);
        $step = new ExecutionStep('report.sales.generate', []);
        $step->markAsRunning();
        $step->markAsCompleted(['report' => []]);

        return ExecutionResult::fromSteps($goal, [$step], 1.0);
    }

    private function failedResult(string $goalText): ExecutionResult
    {
        $goal = Goal::fromText($goalText, AgentType::Ceo);
        $step = new ExecutionStep('report.sales.generate', []);
        $step->markAsRunning();
        $step->markAsFailed('boom');

        return ExecutionResult::fromSteps($goal, [$step], 1.0);
    }

    private function tenant(): int
    {
        return app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid())->id;
    }
}
