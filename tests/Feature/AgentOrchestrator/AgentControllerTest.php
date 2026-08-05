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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_rejectsAnUnknownAgentType(): void
    {
        [, , $token] = $this->registerAgentWithPermissions(['agent.goals.execute']);

        $response = $this->postJson('/api/agents/marketing', ['goal' => 'Do something'], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404); // route-level: 'marketing' fails the {agentType} where() constraint
    }

    public function test_execute_rejectsAnEmptyGoal(): void
    {
        [, , $token] = $this->registerAgentWithPermissions(['agent.goals.execute']);

        $response = $this->postJson('/api/agents/ceo', ['goal' => '   '], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_execute_rejectsARequestWithNoBearerToken(): void
    {
        $response = $this->postJson('/api/agents/ceo', ['goal' => 'Increase sales']);

        $response->assertStatus(401);
    }

    public function test_execute_rejectsAnAgentMissingTheOrchestratorPermission(): void
    {
        [, , $token] = $this->registerAgentWithPermissions([]); // no agent.goals.execute

        $response = $this->postJson('/api/agents/ceo', ['goal' => 'Increase sales'], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
    }

    public function test_getExecution_returns404ForAnUnknownId(): void
    {
        [, , $token] = $this->registerAgentWithPermissions(['agent.executions.read']);

        $response = $this->getJson('/api/agents/executions/999999', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $this->seed(AgentOrchestratorCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
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
