<?php

namespace Tests\Feature\Core;

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
use Database\Seeders\DemoCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnforceRateLimitAction, wired into MCPGatewayController right after the
 * Agent is resolved (see that controller's own docblock for why this is
 * an explicit Action call rather than route middleware). Overrides
 * `mcp.rate_limit_per_minute` down to a small number so the test doesn't
 * need to fire 100+ real HTTP requests to prove the boundary.
 */
class MCPRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoCapabilitiesSeeder::class);
        config(['mcp.rate_limit_per_minute' => 5]);
    }

    public function test_execute_withinLimit_succeedsForEveryRequest(): void
    {
        $token = $this->registerAgentWithPermission('demo.time.read');

        for ($i = 1; $i <= 5; $i++) {
            $this->callDemoTime($token)->assertStatus(200);
        }
    }

    public function test_execute_exceedingLimit_returnsTooManyRequests(): void
    {
        $token = $this->registerAgentWithPermission('demo.time.read');

        for ($i = 1; $i <= 5; $i++) {
            $this->callDemoTime($token)->assertStatus(200);
        }

        $response = $this->callDemoTime($token);

        $response->assertStatus(429);
        $response->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }

    public function test_execute_agentAtLimit_doesNotAffectDifferentAgent(): void
    {
        $tokenA = $this->registerAgentWithPermission('demo.time.read');
        $tokenB = $this->registerAgentWithPermission('demo.time.read');

        for ($i = 1; $i <= 5; $i++) {
            $this->callDemoTime($tokenA)->assertStatus(200);
        }

        $this->callDemoTime($tokenA)->assertStatus(429);

        // Agent B has its own key — a completely separate budget.
        $this->callDemoTime($tokenB)->assertStatus(200);
    }

    private function callDemoTime(string $token)
    {
        return $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.time',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);
    }

    private function registerAgentWithPermission(string $permissionKey): string
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Demo Agent', 'shopping');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
        $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Demo Role', 'demo-role-'.uniqid());
        app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        return app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;
    }
}
