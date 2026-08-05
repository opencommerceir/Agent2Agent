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
use Database\Seeders\AgentOrchestratorCapabilitiesSeeder;
use Database\Seeders\AnalyticsCapabilitiesSeeder;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Database\Seeders\ReportingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * This module's own explicit rule: one failed step must never abort the
 * rest of the plan, and every Execution is strictly scoped to the Tenant
 * that ran it.
 */
class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_aSingleFailedStepDoesNotAbortTheRestOfThePlan(): void
    {
        // Deliberately missing commerce.coupons.create — the coupon step
        // must fail while the other steps in this 4-step plan still run.
        [$tenantId, , $token] = $this->registerAgentWithPermissions([
            'agent.goals.execute',
            'reporting.sales.read',
            'analytics.kpis.read',
            'notifications.messages.send',
        ]);

        $response = $this->postJson('/api/agents/ceo', [
            'goal' => 'Increase sales by 15% this week',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'partial');
        $response->assertJsonCount(4, 'steps');

        $steps = collect($response->json('steps'))->keyBy('capability');

        $this->assertSame('failed', $steps['commerce.coupon.create']['status']);
        $this->assertNotEmpty($steps['commerce.coupon.create']['error']);
        $this->assertSame('completed', $steps['report.sales.generate']['status']);

        // notification.message.send still ran and failed too (no active
        // Template exists in this test) — proving a *second* independent
        // failure also doesn't stop execution.
        $this->assertSame('failed', $steps['notification.message.send']['status']);

        $this->assertDatabaseHas('agent_executions', ['tenant_id' => $tenantId, 'status' => 'partial']);
    }

    public function test_agentCannotSeeAnotherTenantsExecutions(): void
    {
        [, , $tokenA] = $this->registerAgentWithPermissions(['agent.goals.execute', 'agent.executions.read']);
        $execution = $this->postJson('/api/agents/ceo', ['goal' => 'Water the plants'], ['Authorization' => "Bearer {$tokenA}"]);
        $executionId = $execution->json('id');

        [, , $tokenB] = $this->registerAgentWithPermissions(['agent.executions.read']);

        $get = $this->getJson("/api/agents/executions/{$executionId}", ['Authorization' => "Bearer {$tokenB}"]);
        $get->assertStatus(404);

        $list = $this->getJson('/api/agents/executions', ['Authorization' => "Bearer {$tokenB}"]);
        $list->assertStatus(200);
        $list->assertJsonCount(0, 'executions');
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
