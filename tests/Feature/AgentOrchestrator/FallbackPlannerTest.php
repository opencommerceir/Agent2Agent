<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\AgentOrchestrator\Domain\Exceptions\LLMRequestFailedException;
use App\Modules\AgentOrchestrator\Domain\Services\LLMClientInterface;
use Database\Seeders\AgentOrchestratorCapabilitiesSeeder;
use Database\Seeders\AnalyticsCapabilitiesSeeder;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * This stage's own explicit rule: a broken/unreachable LLM must never
 * turn "execute a goal" into a hard failure for an ordinary caller — the
 * exact same "continue past a failure" philosophy Stage 1's own
 * `PlanExecutor` already applies per-step, one level up, per-plan.
 */
class FallbackPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_aFailedLlmCallFallsBackToTheIdenticalDeterministicPlan(): void
    {
        config(['agent-orchestrator.planner.type' => 'llm']);
        $this->app->bind(LLMClientInterface::class, fn () => $this->throwingClient());

        [$tenantId, , $token] = $this->registerAgentWithPermissions([
            'agent.goals.execute', 'reporting.sales.read', 'analytics.kpis.read',
            'commerce.coupons.create', 'notifications.messages.send',
        ]);

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        // Identical to config/agents/ceo.php's own 'sales' rule
        // (DeterministicPlannerTest/GoalExecutionTest's own expectation) —
        // proving the fallback plan is the real deterministic one, not an
        // empty/degraded substitute.
        $this->assertSame(
            ['report.sales.generate', 'analytics.kpi.calculate', 'commerce.coupon.create', 'notification.message.send'],
            array_column($response->json('steps'), 'capability'),
        );

        $this->assertDatabaseHas('agent_executions', ['tenant_id' => $tenantId]);
    }

    public function test_aFailedLlmCallLogsAWarningBeforeFallingBack(): void
    {
        Log::spy();

        config(['agent-orchestrator.planner.type' => 'llm']);
        $this->app->bind(LLMClientInterface::class, fn () => $this->throwingClient());

        [, , $token] = $this->registerAgentWithPermissions(['agent.goals.execute']);

        $this->postJson('/api/agents/ceo', ['goal' => 'Increase sales'], ['Authorization' => "Bearer {$token}"]);

        Log::shouldHaveReceived('warning')->with('LLM planner failed', \Mockery::type('array'))->once();
        Log::shouldHaveReceived('warning')->with('Falling back to deterministic planner', \Mockery::type('array'))->once();
    }

    public function test_whenFallbackIsDisabledTheFailurePropagatesAsAnError(): void
    {
        config([
            'agent-orchestrator.planner.type' => 'llm',
            'agent-orchestrator.planner.fallback_to_deterministic' => false,
        ]);
        $this->app->bind(LLMClientInterface::class, fn () => $this->throwingClient());

        [, , $token] = $this->registerAgentWithPermissions(['agent.goals.execute']);

        $response = $this->postJson('/api/agents/ceo', ['goal' => 'Increase sales'], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(500);
        $response->assertJsonPath('error.code', 'INTERNAL_ERROR');
    }

    private function throwingClient(): LLMClientInterface
    {
        return new class implements LLMClientInterface {
            public function complete(string $prompt, array $options = []): string
            {
                throw new LLMRequestFailedException('simulated network failure');
            }

            public function completeStructured(string $prompt, string $schema, array $options = []): array
            {
                throw new LLMRequestFailedException('simulated network failure');
            }
        };
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        $this->seed(ReportingCapabilitiesSeeder::class);
        $this->seed(AnalyticsCapabilitiesSeeder::class);
        $this->seed(NotificationsCapabilitiesSeeder::class);
        $this->seed(AgentOrchestratorCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'CEO Agent', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Orchestrator', 'orchestrator-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }
}
