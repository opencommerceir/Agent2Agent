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
use Database\Seeders\CommerceCapabilitiesSeeder;
use Database\Seeders\NotificationsCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The literal end-to-end scenario from this stage's own request (Phase 6,
 * Stage 5, §7.30), reshaped around the confirmed, buildable design:
 * `agent.collaboration.delegate` is an ordinary MCP capability an
 * already-authenticated caller invokes explicitly — not an automatic
 * mid-plan detection inside `ExecuteGoalAction` (which the request's own
 * design implied but which cannot work in this codebase's real identity
 * model, see `docs/multi-agent-collaboration.md`'s own "Personas are not
 * identities" section). Tested via `/mcp/v1/execute` — the first test in
 * this module to do so (every prior test used this module's own
 * `/api/agents/*` HTTP surface instead), since no dedicated HTTP route was
 * requested for either of this stage's 2 capabilities.
 */
class MultiAgentCollaborationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ceoDelegatesACouponTaskToSalesAndTheResultIsReal(): void
    {
        [$tenantId, , $token] = $this->registerAgentWithPermissions([
            'agent.collaboration.delegate', 'agent.collaboration.read',
            'commerce.coupons.create', 'notifications.messages.send', 'notifications.templates.manage',
        ]);
        $this->seedPromotionTemplate($tenantId);

        $delegate = $this->postJson('/mcp/v1/execute', [
            'capability' => 'agent.collaboration.delegate',
            'input' => [
                'from_agent' => 'ceo',
                'to_agent' => 'sales',
                'task' => 'Create a 15% discount coupon for summer promotion',
                'priority' => 8,
            ],
        ], ['Authorization' => "Bearer {$token}"]);

        $delegate->assertStatus(200);
        $this->assertNotNull($delegate->json('data.delegation_id'));
        $result = $delegate->json('data.result');
        $this->assertSame('completed', $result['status']);
        $this->assertSame(
            ['commerce.coupon.create', 'notification.message.send'],
            array_column($result['steps'], 'capability'),
        );
        $this->assertSame('sales', $result['agent_type']);

        $this->assertDatabaseHas('delegation_requests', [
            'tenant_id' => $tenantId,
            'from_agent_type' => 'ceo',
            'to_agent_type' => 'sales',
            'priority' => 8,
            'status' => 'completed',
        ]);
        $this->assertDatabaseCount('agent_messages', 2);

        $messages = $this->postJson('/mcp/v1/execute', [
            'capability' => 'agent.collaboration.messages',
            'input' => ['agent_type' => 'sales'],
        ], ['Authorization' => "Bearer {$token}"]);

        $messages->assertStatus(200);
        $this->assertCount(2, $messages->json('data.messages'));
    }

    public function test_delegationToAPersonaTheCallerCannotActuallyUseComesBackAsARealFailedResult(): void
    {
        // agent.collaboration.delegate is granted, but not commerce.coupons.create
        // — delegating to "sales" does not grant it, since there is no
        // separate "Sales Agent" identity with its own permissions. The
        // delegation *mechanism* still succeeds (200) — it's the *nested*
        // result that honestly reports the real failure, the same way
        // agent.goal.execute itself already behaves for any plan with an
        // unauthorized step (never a 403 for a business-level step failure).
        [, , $token] = $this->registerAgentWithPermissions(['agent.collaboration.delegate']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'agent.collaboration.delegate',
            'input' => ['from_agent' => 'ceo', 'to_agent' => 'sales', 'task' => 'Create a promotion coupon'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $this->assertSame('failed', $response->json('data.result.status'));
        $this->assertStringContainsString('Permission', $response->json('data.result.steps.0.error'));

        $this->assertDatabaseHas('delegation_requests', ['from_agent_type' => 'ceo', 'to_agent_type' => 'sales', 'status' => 'completed']);
    }

    public function test_delegateWithoutPermissionReturnsForbidden(): void
    {
        [, , $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'agent.collaboration.delegate',
            'input' => ['from_agent' => 'ceo', 'to_agent' => 'sales', 'task' => 'Create a promotion coupon'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
    }

    public function test_messagesAreScopedPerTenant(): void
    {
        [$tenantA, , $tokenA] = $this->registerAgentWithPermissions([
            'agent.collaboration.delegate', 'commerce.coupons.create', 'notifications.messages.send', 'notifications.templates.manage',
        ]);
        $this->seedPromotionTemplate($tenantA);

        $this->postJson('/mcp/v1/execute', [
            'capability' => 'agent.collaboration.delegate',
            'input' => ['from_agent' => 'ceo', 'to_agent' => 'sales', 'task' => 'Create a promotion coupon'],
        ], ['Authorization' => "Bearer {$tokenA}"])->assertStatus(200);

        [, , $tokenB] = $this->registerAgentWithPermissions(['agent.collaboration.read']);

        $messagesForB = $this->postJson('/mcp/v1/execute', [
            'capability' => 'agent.collaboration.messages',
            'input' => ['agent_type' => 'sales'],
        ], ['Authorization' => "Bearer {$tokenB}"]);

        $messagesForB->assertStatus(200);
        $this->assertCount(0, $messagesForB->json('data.messages'));
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
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
