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
use App\Core\Application\Actions\RegisterCapabilityAction;
use App\Core\Domain\Repositories\CapabilityRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Dedicated pin-tests for MCPExceptionHandler's contract, one per mapped
 * status code. MCPGatewayTest already exercises 401/403/404/422 as part of
 * the broader controller flow — these exist separately so the handler's
 * mapping table itself has an explicit, named regression guard per code,
 * independent of which controller happens to trigger it.
 */
class MCPExceptionHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalidToken_isMappedToUnauthorized(): void
    {
        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
        ], ['Authorization' => 'Bearer oc_agent_does_not_exist']);

        $response->assertStatus(401);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_missingPermission_isMappedToForbidden(): void
    {
        $token = $this->registerAgentWithPermission(null);
        $this->registerSearchCapability();

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_unknownCapability_isMappedToNotFound(): void
    {
        $token = $this->registerAgentWithPermission('commerce.products.read');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.nonexistent.action',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * Simulates a genuinely unexpected failure (e.g. a broken database
     * connection) by making the Capability repository throw a plain
     * RuntimeException — something no Core exception mapping recognizes —
     * and checks both halves of the requirement: the Agent gets a generic
     * message (nothing sensitive leaks when app.debug is off), and the
     * real exception detail still lands in storage/logs via report().
     */
    public function test_unexpectedException_isMappedToInternalErrorHidesDetailsAndLogs(): void
    {
        config(['app.debug' => false]);

        $token = $this->registerAgentWithPermission('commerce.products.read');

        $marker = 'MCP-TEST-SENSITIVE-DETAIL-'.uniqid();
        $capabilities = Mockery::mock(CapabilityRepositoryInterface::class);
        $capabilities->shouldReceive('findByName')->andThrow(new RuntimeException($marker));
        $this->app->instance(CapabilityRepositoryInterface::class, $capabilities);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
            'input' => ['query' => 'laptop'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(500);
        $response->assertJsonPath('error.code', 'INTERNAL_ERROR');
        $response->assertJsonPath('error.message', 'An unexpected error occurred.');
        $response->assertJsonMissingPath('error.trace');

        $logPath = storage_path('logs/laravel.log');
        $this->assertFileExists($logPath);
        $this->assertStringContainsString($marker, file_get_contents($logPath));
    }

    private function registerSearchCapability(): void
    {
        app(RegisterCapabilityAction::class)->execute(
            name: 'commerce.product.search',
            description: 'Search the product catalog',
            inputSchema: ['query' => 'string'],
            outputSchema: ['products' => 'array'],
            requiredPermissions: ['commerce.products.read'],
        );
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
