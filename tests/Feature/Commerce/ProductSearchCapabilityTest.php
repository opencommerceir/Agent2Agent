<?php

namespace Tests\Feature\Commerce;

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
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the full path Commerce wires up, mirroring
 * DemoCapabilitiesTest: capability registered in the Capability Registry
 * (via CommerceCapabilitiesSeeder) + handler registered in
 * CapabilityHandlerRegistry (via CommerceServiceProvider::boot()), both
 * reachable through the real MCP Gateway HTTP routes — plus the
 * tenant-isolation guarantee that was the whole reason the handler
 * contract grew a tenantId parameter (see CapabilityHandlerRegistry's
 * docblock).
 */
class ProductSearchCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CommerceCapabilitiesSeeder::class);
    }

    public function test_search_withMatchingQuery_returnsOnlyActiveMatchingProducts(): void
    {
        [$tenantId, $token] = $this->registerAgentWithPermission('commerce.products.read');

        app(CreateProductAction::class)->execute($tenantId, 'Blue Widget', 'BLUE-WIDGET', 1999, 'USD', status: 'active');
        app(CreateProductAction::class)->execute($tenantId, 'Draft Widget', 'DRAFT-WIDGET', 1999, 'USD', status: 'draft');
        app(CreateProductAction::class)->execute($tenantId, 'Red Gadget', 'RED-GADGET', 999, 'USD', status: 'active');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
            'input' => ['query' => 'Widget', 'limit' => 10],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $names = collect($response->json('data.products'))->pluck('name');

        $this->assertTrue($names->contains('Blue Widget'));
        $this->assertFalse($names->contains('Draft Widget')); // not Active
        $this->assertFalse($names->contains('Red Gadget')); // doesn't match query
    }

    public function test_search_onlyReturnsProductsBelongingToTheCallingAgentsTenant(): void
    {
        [$tenantA, $tokenA] = $this->registerAgentWithPermission('commerce.products.read');
        [$tenantB] = $this->registerAgentWithPermission('commerce.products.read');

        app(CreateProductAction::class)->execute($tenantA, 'Tenant A Widget', 'TENANT-A-WIDGET', 1999, 'USD', status: 'active');
        app(CreateProductAction::class)->execute($tenantB, 'Tenant B Widget', 'TENANT-B-WIDGET', 1999, 'USD', status: 'active');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
            'input' => ['query' => 'Widget', 'limit' => 10],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $response->assertStatus(200);
        $names = collect($response->json('data.products'))->pluck('name');

        $this->assertTrue($names->contains('Tenant A Widget'));
        $this->assertFalse($names->contains('Tenant B Widget'));
    }

    public function test_search_withoutPermission_returnsForbidden(): void
    {
        [$tenantId, $token] = $this->registerAgentWithPermission(null);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
            'input' => ['query' => 'Widget', 'limit' => 10],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function registerAgentWithPermission(?string $permissionKey): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKey !== null) {
            // Permission is global platform vocabulary (not tenant-scoped),
            // so a second call from another test-tenant must reuse the
            // existing row rather than fail on a duplicate key.
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;

            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Reader', 'reader-'.uniqid());
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $token];
    }
}
