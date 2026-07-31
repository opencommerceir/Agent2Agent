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
use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Domain\Services\WooCommerceProductMapper;
use App\Modules\Commerce\Infrastructure\Connectors\WooCommerceProductConnector;
use App\Modules\Commerce\Infrastructure\Http\MockWooCommerceHttpClient;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full Stage 6 scenario over real MCP HTTP requests:
 * commerce.woocommerce.sync syncs 2 products into the tenant's catalog ->
 * commerce.product.search finds one of them (proving the synced rows are
 * indistinguishable from any other Product) -> commerce.woocommerce.get
 * fetches a single product live from the connector (not from the local
 * catalog) -> a simulated WooCommerce API outage maps to 500
 * INTERNAL_ERROR (WooCommerceApiException deliberately implements
 * neither marker interface — see its docblock).
 */
class WooCommerceConnectorCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CommerceCapabilitiesSeeder::class);
    }

    private function useMockConnector(MockWooCommerceHttpClient $client): void
    {
        app(ConnectorRegistry::class)->registerProductConnector(
            'woocommerce',
            new WooCommerceProductConnector($client, new WooCommerceProductMapper(), 'USD'),
        );
    }

    public function test_syncThenSearchThenGetSingleProduct_worksEndToEnd(): void
    {
        $this->useMockConnector(new MockWooCommerceHttpClient());

        [, $token] = $this->registerAgentWithPermissions([
            'commerce.connectors.sync',
            'commerce.connectors.read',
            'commerce.products.read',
        ]);

        // Step 1: sync.
        $sync = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.woocommerce.sync',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $sync->assertStatus(200);
        $sync->assertJsonPath('data.result.success_count', 2);
        $sync->assertJsonPath('data.result.failed_count', 0);

        // Step 2: the synced product is now a normal, searchable Product.
        $search = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.product.search',
            'input' => ['query' => 'T-Shirt', 'limit' => 10],
        ], ['Authorization' => "Bearer {$token}"]);

        $search->assertStatus(200);
        $names = collect($search->json('data.products'))->pluck('name');
        $this->assertTrue($names->contains('WooCommerce T-Shirt'));

        // Step 3: live single-product lookup straight from the connector.
        $get = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.woocommerce.get',
            'input' => ['external_id' => '124'],
        ], ['Authorization' => "Bearer {$token}"]);

        $get->assertStatus(200);
        $get->assertJsonPath('data.product.name', 'WooCommerce Mug');
        $get->assertJsonPath('data.product.priceAmount', 1499);
    }

    public function test_getSingleProduct_withUnknownExternalId_returnsNotFound(): void
    {
        $this->useMockConnector(new MockWooCommerceHttpClient());

        [, $token] = $this->registerAgentWithPermissions(['commerce.connectors.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.woocommerce.get',
            'input' => ['external_id' => 'does-not-exist'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_sync_whenWooCommerceApiFails_returnsInternalError(): void
    {
        $client = new MockWooCommerceHttpClient();
        $client->simulateFailure(true);
        $this->useMockConnector($client);

        [, $token] = $this->registerAgentWithPermissions(['commerce.connectors.sync']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.woocommerce.sync',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(500);
        $response->assertJsonPath('error.code', 'INTERNAL_ERROR');
    }

    public function test_sync_withoutPermission_returnsForbidden(): void
    {
        $this->useMockConnector(new MockWooCommerceHttpClient());

        [, $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.woocommerce.sync',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Sync Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Connector Operator', 'connector-operator-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $token];
    }
}
