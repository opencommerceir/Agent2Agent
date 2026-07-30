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
use App\Core\Domain\ValueObjects\MemberType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real HTTP requests through Laravel's test client against routes/mcp.php
 * -- the same route registered via CoreServiceProvider::loadRoutesFrom(),
 * exercised end to end (routing, FormRequest validation, controller,
 * services, response envelope) without needing a live `artisan serve`.
 */
class MCPGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withValidTokenAndPermission_returnsSuccessEnvelope(): void
    {
        $token = $this->registerAgentWithPermission('commerce.products.read');
        $this->registerSearchCapability();

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
            'input' => ['query' => 'laptop'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta' => ['capability', 'execution_time']]);
        $response->assertJsonPath('meta.capability', 'commerce.product.search');
    }

    public function test_execute_withoutToken_returnsUnauthorized(): void
    {
        $this->registerSearchCapability();

        $response = $this->postJson('/mcp/v1/execute', ['capability' => 'commerce.product.search']);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_execute_withGarbageToken_returnsUnauthorized(): void
    {
        $this->registerSearchCapability();

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
        ], ['Authorization' => 'Bearer oc_agent_totally_invalid']);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_execute_withValidTokenButMissingPermission_returnsForbidden(): void
    {
        $token = $this->registerAgentWithPermission(null);
        $this->registerSearchCapability();

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
            'input' => ['query' => 'laptop'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_execute_withUnknownCapability_returnsCapabilityNotFound(): void
    {
        $token = $this->registerAgentWithPermission('commerce.products.read');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.nonexistent.action',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'CAPABILITY_NOT_FOUND');
    }

    public function test_execute_withMissingRequiredInputField_returnsInvalidInput(): void
    {
        $token = $this->registerAgentWithPermission('commerce.products.read');
        $this->registerSearchCapability();

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    public function test_execute_withoutCapabilityField_returnsInvalidRequest(): void
    {
        $token = $this->registerAgentWithPermission('commerce.products.read');

        $response = $this->postJson('/mcp/v1/execute', [], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INVALID_REQUEST');
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
