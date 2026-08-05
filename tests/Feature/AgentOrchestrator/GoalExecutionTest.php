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
use App\Modules\Notifications\Application\Actions\CreateTemplateAction;
use Database\Seeders\AgentOrchestratorCapabilitiesSeeder;
use Database\Seeders\AnalyticsCapabilitiesSeeder;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The literal end-to-end scenario from this module's own request:
 * POST /api/agents/ceo with a "sales" goal -> the 5-step
 * DeterministicPlanner plan runs -> every step completes -> a real
 * summary is produced -> the run is retrievable afterward via
 * GET /api/agents/executions and /api/agents/executions/{id}.
 */
class GoalExecutionTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIRED_PERMISSIONS = [
        'agent.goals.execute',
        'agent.executions.read',
        'reporting.sales.read',
        'analytics.kpis.read',
        'commerce.coupons.create',
        'notifications.messages.send',
        'notifications.templates.manage',
    ];

    public function test_ceoSalesGoal_runsAllFiveStepsToCompletion(): void
    {
        [$tenantId, , $token] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);
        $this->seedPromotionTemplate($tenantId);

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('agent_type', 'ceo');
        $response->assertJsonCount(5, 'steps');

        $capabilities = array_column($response->json('steps'), 'capability');
        $this->assertSame([
            'report.sales.generate',
            'analytics.kpi.calculate',
            'analytics.kpi.calculate',
            'commerce.coupon.create',
            'notification.message.send',
        ], $capabilities);

        foreach ($response->json('steps') as $step) {
            $this->assertSame('completed', $step['status'], "Step [{$step['capability']}] did not complete: ".json_encode($step['error']));
        }

        $response->assertJsonPath('status', 'completed');
        $this->assertNotEmpty($response->json('summary'));
        $this->assertIsFloat($response->json('execution_time'));

        $this->assertDatabaseHas('agent_executions', ['tenant_id' => $tenantId, 'status' => 'completed']);
        $this->assertDatabaseCount('agent_execution_steps', 5);
    }

    public function test_executionIsRetrievableAfterwardById(): void
    {
        [$tenantId, , $token] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);
        $this->seedPromotionTemplate($tenantId);

        $execute = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);
        $executionId = $execute->json('id');

        $get = $this->getJson("/api/agents/executions/{$executionId}", ['Authorization' => "Bearer {$token}"]);

        $get->assertStatus(200);
        $get->assertJsonPath('id', $executionId);
        $get->assertJsonCount(5, 'steps');
    }

    public function test_listExecutions_returnsThisTenantsPastRuns(): void
    {
        [$tenantId, , $token] = $this->registerAgentWithPermissions(self::REQUIRED_PERMISSIONS);
        $this->seedPromotionTemplate($tenantId);

        $this->postJson('/api/agents/support', ['goal' => 'Review open support tickets'], ['Authorization' => "Bearer {$token}"]);
        $this->postJson('/api/agents/finance', ['goal' => 'Review finance and revenue'], ['Authorization' => "Bearer {$token}"]);

        $list = $this->getJson('/api/agents/executions', ['Authorization' => "Bearer {$token}"]);

        $list->assertStatus(200);
        $list->assertJsonCount(2, 'executions');
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
