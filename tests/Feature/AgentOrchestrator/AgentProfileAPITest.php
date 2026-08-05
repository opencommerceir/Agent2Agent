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

class AgentProfileAPITest extends TestCase
{
    use RefreshDatabase;

    public function test_listProfiles_returnsEveryConfiguredProfile(): void
    {
        $token = $this->registerAgentWithProfilesPermission();

        $response = $this->getJson('/api/agents/profiles', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $types = array_column($response->json('profiles'), 'type');
        $this->assertContains('ceo', $types);
        $this->assertContains('sales', $types);
        $this->assertContains('support', $types);
        $this->assertContains('finance', $types);
    }

    public function test_getProfile_returnsCeoProfileDetail(): void
    {
        $token = $this->registerAgentWithProfilesPermission();

        $response = $this->getJson('/api/agents/profiles/ceo', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('type', 'ceo');
        $response->assertJsonPath('name', 'CEO Agent');
        $this->assertArrayHasKey('sales', $response->json('planningRules'));
        $this->assertArrayHasKey('commerce.coupon.create', $response->json('defaultInputs'));
    }

    public function test_getProfile_forAnUnknownAgentTypeReturnsNotFound(): void
    {
        $token = $this->registerAgentWithProfilesPermission();

        $response = $this->getJson('/api/agents/profiles/marketing', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_listProfiles_rejectsAnAgentMissingThePermission(): void
    {
        $token = $this->registerAgentWithProfilesPermission(grantPermission: false);

        $response = $this->getJson('/api/agents/profiles', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
    }

    private function registerAgentWithProfilesPermission(bool $grantPermission = true): string
    {
        $this->seed(AgentOrchestratorCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($grantPermission) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Orchestrator', 'orchestrator-'.uniqid());
            $permissionKey = 'agent.profiles.read';
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        return app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;
    }
}
