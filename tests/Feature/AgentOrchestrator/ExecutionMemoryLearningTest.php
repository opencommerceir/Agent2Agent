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
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPattern;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionPatternRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\Notifications\Application\Actions\CreateTemplateAction;
use Database\Seeders\AgentOrchestratorCapabilitiesSeeder;
use Database\Seeders\AnalyticsCapabilitiesSeeder;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The literal end-to-end scenario from this stage's own request (Phase 6,
 * Stage 4, §7.29), entirely through the module's own `/api/agents/*` HTTP
 * surface — the same convention every other test in this module already
 * uses (`GoalExecutionTest`/`CEOAgentTest`/`AgentControllerTest`, none of
 * which exercise raw `/mcp/v1/execute`, even though the same capabilities
 * are also reachable there).
 */
class ExecutionMemoryLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_aSuccessfulExecutionIsRecordedAndLearnedFromInOnePass(): void
    {
        [$tenantId, , $token] = $this->registerCeoAgent();
        $this->seedPromotionTemplate($tenantId);

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'completed');

        // Part A: already-existing agent_executions/agent_execution_steps
        // (§7.26) recorded the run — no new "ExecutionMemory" table needed.
        $this->assertDatabaseHas('agent_executions', ['tenant_id' => $tenantId, 'status' => 'completed']);

        // Part B: a brand-new ExecutionPattern was learned from it.
        $this->assertDatabaseHas('execution_patterns', [
            'tenant_id' => $tenantId,
            'goal_pattern' => 'sales',
            'agent_type' => 'ceo',
            'usage_count' => 1,
            'success_rate' => 1.0,
        ]);
    }

    public function test_twoRealSimilarGoalsAccumulateIntoOneLearnedPatternNotTwo(): void
    {
        [$tenantId, , $token] = $this->registerCeoAgent();
        $this->seedPromotionTemplate($tenantId);

        $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200)->assertJsonPath('status', 'completed');

        $this->postJson('/api/agents/ceo', [
            'goal' => 'Boost sales this month',
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200)->assertJsonPath('status', 'completed');

        $this->assertDatabaseCount('execution_patterns', 1);
        $this->assertDatabaseHas('execution_patterns', [
            'tenant_id' => $tenantId,
            'goal_pattern' => 'sales',
            'usage_count' => 2,
            'success_rate' => 1.0,
        ]);
    }

    public function test_aSimilarGoalUsesTheLearnedPlanInsteadOfRePlanning(): void
    {
        [$tenantId, , $token] = $this->registerCeoAgent();
        $this->seedPromotionTemplate($tenantId);

        // Seeded directly with only 2 capabilities — deliberately fewer
        // than config/agents/ceo.php's own 4-capability 'sales' rule, so a
        // response with exactly these 2 steps can only have come from this
        // learned pattern, never from DeterministicPlanner re-planning.
        app(ExecutionPatternRepositoryInterface::class)->save(ExecutionPattern::create(
            tenantId: $tenantId,
            goalPattern: 'sales',
            agentType: AgentType::Ceo,
            successfulCapabilities: ['report.sales.generate', 'analytics.kpi.calculate'],
            now: new DateTimeImmutable(),
        ));

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'steps');
        $this->assertSame(
            ['report.sales.generate', 'analytics.kpi.calculate'],
            array_column($response->json('steps'), 'capability'),
        );

        $this->assertDatabaseHas('execution_patterns', [
            'tenant_id' => $tenantId,
            'goal_pattern' => 'sales',
            'usage_count' => 2,
        ]);
    }

    public function test_memoryInsights_reflectsRealExecutionHistory(): void
    {
        [$tenantId, , $token] = $this->registerCeoAgent();
        $this->seedPromotionTemplate($tenantId);

        $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200);

        $response = $this->getJson('/api/agents/memory/insights?agent_type=ceo', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('insights.total_executions'));
        $this->assertEqualsWithDelta(1.0, $response->json('insights.success_rate'), 0.0001);
        $this->assertContains(
            'report.sales.generate',
            array_column($response->json('insights.most_used_capabilities'), 'capability'),
        );
        $this->assertSame(['Increase sales by 15% this week'], $response->json('insights.recent_goals'));
    }

    public function test_memorySuggest_returnsNullBeforeAnythingHasBeenLearned(): void
    {
        [, , $token] = $this->registerCeoAgent();

        $response = $this->postJson('/api/agents/memory/suggest', [
            'goal' => 'Increase sales this week',
            'agent_type' => 'ceo',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $this->assertNull($response->json('suggested_plan'));
    }

    public function test_memorySuggest_returnsTheRealLearnedPlanAfterOneSuccessfulExecution(): void
    {
        [$tenantId, , $token] = $this->registerCeoAgent();
        $this->seedPromotionTemplate($tenantId);

        $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200);

        $response = $this->postJson('/api/agents/memory/suggest', [
            'goal' => 'Boost sales this month',
            'agent_type' => 'ceo',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('suggested_plan'));
        $this->assertSame(
            ['report.sales.generate', 'analytics.kpi.calculate', 'commerce.coupon.create', 'notification.message.send'],
            array_column($response->json('suggested_plan.steps'), 'capability'),
        );
    }

    public function test_memoryEndpoints_rejectAnAgentMissingThePermission(): void
    {
        [, , $token] = $this->registerCeoAgent(includeMemoryPermission: false);

        $insights = $this->getJson('/api/agents/memory/insights?agent_type=ceo', ['Authorization' => "Bearer {$token}"]);
        $insights->assertStatus(403);

        $suggest = $this->postJson('/api/agents/memory/suggest', [
            'goal' => 'Increase sales this week', 'agent_type' => 'ceo',
        ], ['Authorization' => "Bearer {$token}"]);
        $suggest->assertStatus(403);
    }

    public function test_memoryInsightsAndSuggest_neverLeakAnotherTenantsLearnedHistory(): void
    {
        [$tenantA, , $tokenA] = $this->registerCeoAgent();
        $this->seedPromotionTemplate($tenantA);
        $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$tokenA}"])->assertStatus(200);

        [, , $tokenB] = $this->registerCeoAgent();

        $insights = $this->getJson('/api/agents/memory/insights?agent_type=ceo', ['Authorization' => "Bearer {$tokenB}"]);
        $insights->assertStatus(200);
        $this->assertSame(0, $insights->json('insights.total_executions'));

        $suggest = $this->postJson('/api/agents/memory/suggest', [
            'goal' => 'Boost sales this month', 'agent_type' => 'ceo',
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $suggest->assertStatus(200);
        $this->assertNull($suggest->json('suggested_plan'));
    }

    /**
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerCeoAgent(bool $includeMemoryPermission = true): array
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

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'CEO', 'ceo-'.uniqid());

        $permissions = [
            'agent.goals.execute', 'agent.executions.read', 'reporting.sales.read', 'reporting.revenue.read',
            'analytics.kpis.read', 'commerce.coupons.create', 'notifications.messages.send',
            'notifications.templates.manage',
        ];
        if ($includeMemoryPermission) {
            $permissions[] = 'agent.memory.read';
        }

        foreach ($permissions as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }

    private function seedPromotionTemplate(int $tenantId): void
    {
        app(CreateTemplateAction::class)->execute(
            tenantId: $tenantId,
            type: 'promotion_announcement',
            channelType: 'email',
            subjectTemplate: '{{discount_percent}}% off this week',
            bodyTemplate: 'Enjoy {{discount_percent}}% off your next order.',
        );
    }
}
