<?php

namespace Tests\Feature\MCP;

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
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Stage 7 (API Versioning) end to end, through Laravel's real test client
 * against routes/mcp.php — the same "real HTTP requests, no mocked
 * routing" style MCPGatewayTest already established for v1 alone.
 *
 * The one deliberate departure from the original request's own example
 * test (test_header_based_versioning: hitting /mcp/v1/execute with an
 * Accept: v2 header and expecting a v2-shaped response) is
 * test_execute_v1UrlWithV2AcceptHeader_stillReturnsV1Format below — see
 * VersionDetectorInterface's own docblock for the full reasoning:
 * an explicit URL version must never be silently overridden by a header,
 * or a v1 integration's response shape could change without any code on
 * its own end changing. Confirmed with the user before implementing it
 * the other way.
 */
class ApiVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1Execute_returnsV1Envelope(): void
    {
        $this->seed(DemoCapabilitiesSeeder::class);
        $token = $this->registerAgentWithPermission('demo.echo.execute');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.echo',
            'input' => ['message' => 'hello'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['echo', 'timestamp'], 'meta' => ['capability', 'execution_time']]);
        $this->assertArrayNotHasKey('result', $response->json());
    }

    public function test_v2Execute_returnsV2Envelope(): void
    {
        $this->seed(DemoCapabilitiesSeeder::class);
        $token = $this->registerAgentWithPermission('demo.echo.execute');

        $response = $this->postJson('/mcp/v2/execute', [
            'capability' => 'demo.tools.echo',
            'input' => ['message' => 'hello'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'result' => ['echo', 'timestamp'],
            'metadata' => ['api_version', 'capability', 'execution_time', 'timestamp'],
        ]);
        $response->assertJsonPath('metadata.api_version', 'v2');
    }

    public function test_v1Execute_returnsDeprecationHeaders(): void
    {
        $this->seed(DemoCapabilitiesSeeder::class);
        $token = $this->registerAgentWithPermission('demo.echo.execute');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.echo',
            'input' => ['message' => 'hello'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertHeader('X-API-Version', 'v1');
        $response->assertHeader('Deprecation', 'true');
        $response->assertHeader('Sunset', 'Sat, 01 Jan 2028 00:00:00 GMT');
        $response->assertHeader('Link', '<https://docs.opencommerce.ir/migration/v1-to-v2>; rel="successor-version"');
        $this->assertStringContainsString('v1 is deprecated', $response->headers->get('Warning'));
    }

    public function test_v2Execute_returnsNoDeprecationHeaders(): void
    {
        $this->seed(DemoCapabilitiesSeeder::class);
        $token = $this->registerAgentWithPermission('demo.echo.execute');

        $response = $this->postJson('/mcp/v2/execute', [
            'capability' => 'demo.tools.echo',
            'input' => ['message' => 'hello'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertHeader('X-API-Version', 'v2');
        $response->assertHeaderMissing('Deprecation');
        $response->assertHeaderMissing('Sunset');
    }

    public function test_v1Capabilities_alsoReturnsDeprecationHeaders(): void
    {
        $this->seed(DemoCapabilitiesSeeder::class);
        $token = $this->registerAgentWithPermission(null);

        $response = $this->getJson('/mcp/v1/capabilities', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertHeader('Deprecation', 'true');
    }

    public function test_v2Capabilities_returnsV2Envelope(): void
    {
        $this->seed(DemoCapabilitiesSeeder::class);
        $token = $this->registerAgentWithPermission(null);

        $response = $this->getJson('/mcp/v2/capabilities', ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['capabilities', 'metadata' => ['api_version', 'count', 'timestamp']]);
    }

    /**
     * The one deliberate departure from the original request's literal
     * example — see this class's own docblock.
     */
    public function test_execute_v1UrlWithV2AcceptHeader_stillReturnsV1Format(): void
    {
        $this->seed(DemoCapabilitiesSeeder::class);
        $token = $this->registerAgentWithPermission('demo.echo.execute');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.echo',
            'input' => ['message' => 'hello'],
        ], [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.opencommerce.v2+json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
        $response->assertHeader('X-API-Version', 'v1');
    }

    public function test_bothVersions_returnTheSameUnderlyingData(): void
    {
        $this->seed(DemoCapabilitiesSeeder::class);
        $token = $this->registerAgentWithPermission('demo.echo.execute');

        $v1 = $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.echo',
            'input' => ['message' => 'same payload'],
        ], ['Authorization' => "Bearer {$token}"]);

        $v2 = $this->postJson('/mcp/v2/execute', [
            'capability' => 'demo.tools.echo',
            'input' => ['message' => 'same payload'],
        ], ['Authorization' => "Bearer {$token}"]);

        $this->assertSame($v1->json('data.echo'), $v2->json('result.echo'));
    }

    public function test_v1Execute_logsADeprecationWarning(): void
    {
        Log::spy();

        $this->seed(DemoCapabilitiesSeeder::class);
        $token = $this->registerAgentWithPermission('demo.echo.execute');

        $this->postJson('/mcp/v1/execute', [
            'capability' => 'demo.tools.echo',
            'input' => ['message' => 'hello'],
        ], ['Authorization' => "Bearer {$token}"]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'Deprecated API version used'
                && $context['version'] === 'v1'
                && $context['endpoint'] === 'mcp/v1/execute');
    }

    private function registerAgentWithPermission(?string $permissionKey): string
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKey !== null) {
            $permission = app(CreatePermissionAction::class)->execute($permissionKey);
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Reader', 'reader-'.uniqid());
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permission->id);
            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        return app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;
    }
}
