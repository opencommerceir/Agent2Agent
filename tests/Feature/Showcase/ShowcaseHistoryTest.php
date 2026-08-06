<?php

namespace Tests\Feature\Showcase;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Application\DTOs\AgentData;
use App\Core\Application\DTOs\AuthContext;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\AgentOrchestrator\Application\Actions\ExecuteGoalAction;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType as PersonaType;
use App\Modules\Notifications\Application\Actions\CreateTemplateAction;
use Database\Seeders\AgentOrchestratorCapabilitiesSeeder;
use Database\Seeders\AnalyticsCapabilitiesSeeder;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Database\Seeders\CRMCapabilitiesSeeder;
use Database\Seeders\DemoShowcaseSeeder;
use Database\Seeders\FinanceCapabilitiesSeeder;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /showcase/history[/{id}]` (Phase 3, §7.33) — read-only, reusing
 * `ListExecutionsAction`/`GetExecutionResultAction`/`GetReasoningTraceAction`/
 * `ExplainReasoningAction`, the last two real and tested since §7.31 but
 * with no caller anywhere in this codebase until this stage.
 */
class ShowcaseHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = [
        'agent.goals.execute',
        'agent.executions.read',
        'agent.reasoning.read',
        'reporting.sales.read',
        'reporting.revenue.read',
        'analytics.kpis.read',
        'commerce.coupons.create',
        'notifications.messages.send',
        'notifications.templates.manage',
        'crm.tickets.read',
        'finance.invoices.read',
    ];

    public function test_history_returnsOnlyTheDemoTenantsOwnExecutions(): void
    {
        [$demoTenantId, $demoAgentData] = $this->seedDemoTenant();

        // A real execution for the demo tenant.
        $context = AuthContext::forAgent($demoAgentData);
        app(ExecuteGoalAction::class)->execute('Review our revenue this quarter', PersonaType::Ceo, $context);

        // A completely unrelated Tenant with its own execution — history
        // must never show this, even though nothing about ListExecutionsAction
        // itself would prevent it if this Controller were ever accidentally
        // parametrized to a different tenant id.
        $otherTenant = app(CreateTenantAction::class)->execute('Someone Else Inc', 'someone-else-'.uniqid());
        $otherOrg = app(CreateOrganizationAction::class)->execute($otherTenant->id, 'Other Org', 'other-org-'.uniqid());
        $otherAgent = app(RegisterAgentAction::class)->execute($otherTenant->id, $otherOrg->id, 'Other Agent', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($otherOrg->id, MemberType::Agent, $otherAgent->id);
        $otherRole = app(CreateRoleAction::class)->execute($otherTenant->id, 'Role', 'role-'.uniqid());
        foreach (self::PERMISSIONS as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($otherRole->id, $permissionId);
        }
        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $otherAgent->id, $otherRole->id);
        $otherContext = AuthContext::forAgent(AgentData::fromEntity(
            app(AgentRepositoryInterface::class)->findById($otherAgent->id)
        ));
        app(ExecuteGoalAction::class)->execute('Review our revenue this quarter', PersonaType::Ceo, $otherContext);

        $response = $this->get('/showcase/history');

        $response->assertStatus(200);
        $executions = $response->json('executions');
        $this->assertNotEmpty($executions);

        foreach ($executions as $execution) {
            $this->assertDatabaseHas('agent_executions', [
                'id' => $execution['id'],
                'tenant_id' => $demoTenantId,
            ]);
        }
    }

    public function test_historyShow_returnsTheRealPersistedReasoningTraceForAPastExecution(): void
    {
        [, $demoAgentData] = $this->seedDemoTenant();

        $context = AuthContext::forAgent($demoAgentData);
        $result = app(ExecuteGoalAction::class)->execute('Increase sales by 15% this week', PersonaType::Ceo, $context);

        $response = $this->get('/showcase/history/'.$result->id);

        $response->assertStatus(200);
        $response->assertJsonPath('id', $result->id);
        $response->assertJsonPath('goal', 'Increase sales by 15% this week');
        $response->assertJsonCount(4, 'steps');
        $response->assertJsonStructure([
            'pre_reasoning' => ['thoughts', 'confidence_score', 'decision'],
            'post_reasoning' => ['thoughts', 'confidence_score', 'decision'],
        ]);
        $this->assertNotNull($response->json('explanation'));
    }

    public function test_historyShow_forANonexistentExecution_returns404(): void
    {
        $this->seedDemoTenant();

        $response = $this->get('/showcase/history/999999');

        $response->assertStatus(404);
    }

    /**
     * @return array{0: int, 1: AgentData}
     */
    private function seedDemoTenant(): array
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        $this->seed(ReportingCapabilitiesSeeder::class);
        $this->seed(AnalyticsCapabilitiesSeeder::class);
        $this->seed(NotificationsCapabilitiesSeeder::class);
        $this->seed(CRMCapabilitiesSeeder::class);
        $this->seed(FinanceCapabilitiesSeeder::class);
        $this->seed(AgentOrchestratorCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Demo Showcase Store', DemoShowcaseSeeder::TENANT_SLUG);
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Demo Showcase HQ', DemoShowcaseSeeder::TENANT_SLUG.'-hq');
        $agentData = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, DemoShowcaseSeeder::DEMO_AGENT_NAME, 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agentData->id);

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Showcase Operator', DemoShowcaseSeeder::TENANT_SLUG.'-operator');

        foreach (self::PERMISSIONS as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agentData->id, $role->id);

        app(CreateTemplateAction::class)->execute(
            tenantId: $tenant->id,
            type: 'promotion_announcement',
            channelType: 'email',
            subjectTemplate: '{{discount_percent}}% off this week',
            bodyTemplate: 'Enjoy {{discount_percent}}% off your next order.',
        );

        return [$tenant->id, $agentData];
    }
}
