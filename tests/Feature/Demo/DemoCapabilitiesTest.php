<?php

namespace Tests\Feature\Demo;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use Database\Seeders\DemoCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the full path Demo wires up: capability registered in the
 * Capability Registry (Core, via DemoCapabilitiesSeeder) + handler
 * registered in CapabilityHandlerRegistry (Core, via
 * DemoServiceProvider::boot()), both reachable end to end through the
 * real MCP Gateway HTTP routes. This is the proof that "a Domain Module
 * registers a capability and a handler, MCP just routes to it" — the
 * exact mechanism the Phase 1 review flagged as undecided — actually
 * works, not only that the pieces exist.
 */
class DemoCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoCapabilitiesSeeder::class);
    }

    public function test_demoEcho_withValidMessage_returnsEchoAndTimestamp(): void
    {
        $token = $this->registerAgentWithPermission('demo.echo.execute');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.echo',
            'input' => ['message' => 'Hello, Agent!'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.echo', 'Hello, Agent!');
        $response->assertJsonStructure(['data' => ['echo', 'timestamp']]);
    }

    public function test_demoEcho_withoutMessage_returnsValidationError(): void
    {
        $token = $this->registerAgentWithPermission('demo.echo.execute');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.echo',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_demoTime_returnsUtcAndUnixTimestamp(): void
    {
        $token = $this->registerAgentWithPermission('demo.time.read');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.time',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['utc', 'unix']]);
        $this->assertIsInt($response->json('data.unix'));
    }

    public function test_demoCalculator_withMultiply_returnsCorrectResult(): void
    {
        $token = $this->registerAgentWithPermission('demo.calculator.execute');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.calculator',
            'input' => ['operation' => 'multiply', 'a' => 6, 'b' => 7],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        // JSON has no distinct float type: 42.0 serializes as 42, so it
        // decodes back as an int, not a float — assert on the numeric
        // value, not the PHP type.
        $response->assertJsonPath('data.result', 42);
    }

    public function test_demoCalculator_withDivisionByZero_returnsValidationError(): void
    {
        $token = $this->registerAgentWithPermission('demo.calculator.execute');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.calculator',
            'input' => ['operation' => 'divide', 'a' => 10, 'b' => 0],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_demoCalculator_withInvalidOperation_returnsValidationError(): void
    {
        $token = $this->registerAgentWithPermission('demo.calculator.execute');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.calculator',
            'input' => ['operation' => 'modulo', 'a' => 10, 'b' => 3],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_discoverCapabilities_listsAllThreeDemoCapabilities(): void
    {
        $token = $this->registerAgentWithPermission('demo.echo.execute');

        $response = $this->getJson('/mcp/v1/capabilities', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $names = collect($response->json('data.capabilities'))->pluck('name');

        $this->assertTrue($names->contains('demo.tools.echo'));
        $this->assertTrue($names->contains('demo.tools.time'));
        $this->assertTrue($names->contains('demo.tools.calculator'));
    }

    private function registerAgentWithPermission(string $permissionKey): string
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Demo Agent', 'shopping');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $permission = app(CreatePermissionAction::class)->execute($permissionKey);
        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Demo Role', 'demo-role-'.uniqid());
        app(AssignPermissionToRoleAction::class)->execute($role->id, $permission->id);
        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        return app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;
    }
}
