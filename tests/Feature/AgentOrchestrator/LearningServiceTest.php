<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPattern;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionMemoryRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionPatternRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Services\LearningServiceInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `LearningService` against real Repositories (Phase 6, Stage 4, §7.29) —
 * `suggestPlan()` reads `ExecutionPatternRepositoryInterface` (this
 * stage's own new table); `getInsights()` reads the *existing*
 * `ExecutionMemoryRepositoryInterface` (§7.26) directly, proving Part A of
 * this stage's own request is genuinely served by reuse, not a second,
 * parallel execution log — see `docs/execution-memory.md`.
 */
class LearningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestPlan_returnsNullWhenNoPatternExistsYet(): void
    {
        $tenantId = $this->tenant();
        $goal = Goal::fromText('Increase sales this week', AgentType::Ceo);

        $plan = app(LearningServiceInterface::class)->suggestPlan($goal, $tenantId);

        $this->assertNull($plan);
    }

    public function test_suggestPlan_returnsALearnedPlanUsingRealAgentProfileDefaultInputs(): void
    {
        $tenantId = $this->tenant();
        $this->seedPattern($tenantId, 'sales', ['report.sales.generate', 'analytics.kpi.calculate']);

        $goal = Goal::fromText('Boost sales this month', AgentType::Ceo);
        $plan = app(LearningServiceInterface::class)->suggestPlan($goal, $tenantId);

        $this->assertNotNull($plan);
        $this->assertSame(
            ['report.sales.generate', 'analytics.kpi.calculate'],
            array_map(fn (ExecutionStep $step) => $step->capability, $plan->steps),
        );
        // config/agents/ceo.php's own default_inputs for report.sales.generate resolve {date:N} tokens for real.
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $plan->steps[0]->input['start_date']);
    }

    public function test_suggestPlan_ignoresAPatternBelowTheMinimumSuccessRate(): void
    {
        $tenantId = $this->tenant();
        // 3 failures after the initial success brings success_rate to 0.25
        // (1.0 -> 0.5 -> 0.333 -> 0.25), below LearningService's own 0.5 floor.
        $this->seedPattern($tenantId, 'sales', ['report.sales.generate'], extraFailures: 3);

        $goal = Goal::fromText('Boost sales this month', AgentType::Ceo);
        $plan = app(LearningServiceInterface::class)->suggestPlan($goal, $tenantId);

        $this->assertNull($plan);
    }

    public function test_suggestPlan_neverLeaksAnotherTenantsLearnedPattern(): void
    {
        $tenantA = $this->tenant();
        $tenantB = $this->tenant();
        $this->seedPattern($tenantA, 'sales', ['report.sales.generate']);

        $goal = Goal::fromText('Boost sales this month', AgentType::Ceo);

        $this->assertNull(app(LearningServiceInterface::class)->suggestPlan($goal, $tenantB));
        $this->assertNotNull(app(LearningServiceInterface::class)->suggestPlan($goal, $tenantA));
    }

    public function test_getInsights_aggregatesThisTenantsRealExecutionHistory(): void
    {
        [$tenantId, $agentId] = $this->tenantWithAgent();
        $memory = app(ExecutionMemoryRepositoryInterface::class);

        $memory->save($this->successfulResult('Increase sales this week', 'report.sales.generate'), $tenantId, $agentId, AgentType::Ceo);
        $memory->save($this->successfulResult('Boost sales this month', 'report.sales.generate'), $tenantId, $agentId, AgentType::Ceo);
        $memory->save($this->failedResult('Review revenue this quarter'), $tenantId, $agentId, AgentType::Ceo);

        $insights = app(LearningServiceInterface::class)->getInsights($tenantId, AgentType::Ceo);

        $this->assertSame(3, $insights['total_executions']);
        $this->assertEqualsWithDelta(2 / 3, $insights['success_rate'], 0.0001);
        $this->assertSame('report.sales.generate', $insights['most_used_capabilities'][0]['capability']);
        $this->assertSame(2, $insights['most_used_capabilities'][0]['count']);
        $this->assertCount(3, $insights['recent_goals']);
    }

    public function test_getInsights_withNoHistoryReturnsZeroedOutStats(): void
    {
        $tenantId = $this->tenant();

        $insights = app(LearningServiceInterface::class)->getInsights($tenantId, AgentType::Ceo);

        $this->assertSame(0, $insights['total_executions']);
        $this->assertSame(0.0, $insights['success_rate']);
        $this->assertSame([], $insights['most_used_capabilities']);
    }

    /**
     * @param list<string> $capabilities
     */
    private function seedPattern(int $tenantId, string $goalPattern, array $capabilities, int $extraFailures = 0): void
    {
        $repository = app(ExecutionPatternRepositoryInterface::class);

        $pattern = ExecutionPattern::create(
            tenantId: $tenantId,
            goalPattern: $goalPattern,
            agentType: AgentType::Ceo,
            successfulCapabilities: $capabilities,
            now: new DateTimeImmutable(),
        );
        $repository->save($pattern);

        for ($i = 0; $i < $extraFailures; $i++) {
            $pattern->recordOutcome(false, [], new DateTimeImmutable());
        }

        if ($extraFailures > 0) {
            $repository->save($pattern);
        }
    }

    private function successfulResult(string $goalText, string $capability): ExecutionResult
    {
        $goal = Goal::fromText($goalText, AgentType::Ceo);
        $step = new ExecutionStep($capability, []);
        $step->markAsRunning();
        $step->markAsCompleted(['ok' => true]);

        return ExecutionResult::fromSteps($goal, [$step], 1.0);
    }

    private function failedResult(string $goalText): ExecutionResult
    {
        $goal = Goal::fromText($goalText, AgentType::Ceo);
        $step = new ExecutionStep('report.revenue.generate', []);
        $step->markAsRunning();
        $step->markAsFailed('boom');

        return ExecutionResult::fromSteps($goal, [$step], 1.0);
    }

    private function tenant(): int
    {
        return app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid())->id;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function tenantWithAgent(): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'CEO Agent', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return [$tenant->id, $agent->id];
    }
}
