<?php

namespace Tests\Feature\Shipping;

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
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Shipping\Application\Services\ShippingProviderRegistry;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;
use App\Modules\Shipping\Infrastructure\Http\MockShippingHttpClient;
use App\Modules\Shipping\Infrastructure\Providers\MockShippingProviderAdapter;
use Database\Seeders\ShippingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full Phase 4 Stage 2 (Shipping Provider Connector) scenario over
 * real MCP HTTP requests: live rates from the Mock provider -> a real
 * local Shipment (Stage 1's own shipping.shipment.create) -> handing it
 * to the Mock provider (a real provider tracking number persisted) ->
 * syncing tracking (2 new events, Shipment status becomes in_transit) ->
 * a simulated provider failure -> tenant isolation.
 */
class ShippingProviderCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullProviderScenario(): void
    {
        $this->seed(ShippingCapabilitiesSeeder::class);

        [$tenantA, $agentA, $tokenA] = $this->registerAgentWithPermissions([
            'shipping.methods.create', 'shipping.shipments.create', 'shipping.shipments.read',
            'shipping.providers.read', 'shipping.providers.create', 'shipping.providers.sync',
        ]);

        // Step 1: live rates from the Mock provider — 3 rates, matching the fixture.
        $rates = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.provider.rates',
            'input' => [
                'weight_grams' => 2500,
                'destination' => ['street' => '123 Main St', 'city' => 'Springfield', 'state' => 'IL', 'postalCode' => '62704', 'country' => 'US'],
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $rates->assertStatus(200);
        $this->assertCount(3, $rates->json('data.rates'));
        $this->assertSame('STANDARD', $rates->json('data.rates.0.serviceCode'));
        $this->assertSame(750, $rates->json('data.rates.0.costAmount'));

        // Step 2: a real local Shipment, from a real Order (Stage 1's own flow).
        $product = app(CreateProductAction::class)->execute($tenantA, 'Widget', 'SKU-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $product->id, 10));
        $cart = app(AddToCartAction::class)->execute($tenantA, MemberType::Agent, $agentA, $product->id, 1);
        $order = app(PlaceOrderAction::class)->execute(tenantId: $tenantA, agentId: $agentA, cartId: $cart->id);

        $method = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.method.create',
            'input' => ['name' => 'Standard', 'base_rate' => 500, 'rate_per_kg' => 100, 'estimated_days_min' => 2, 'estimated_days_max' => 5],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $methodId = $method->json('data.method.id');

        $createShipment = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.create',
            'input' => ['order_id' => $order->id, 'shipping_method_id' => $methodId],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $shipmentId = $createShipment->json('data.shipment.id');

        // Step 3: fulfill via the Mock provider — a real provider tracking number.
        $fulfill = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.provider.fulfill',
            'input' => ['shipment_id' => $shipmentId],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $fulfill->assertStatus(200);
        $this->assertSame('mock', $fulfill->json('data.provider_shipment.provider'));
        $providerTrackingNumber = $fulfill->json('data.provider_shipment.providerTrackingNumber');
        $this->assertMatchesRegularExpression('/^TRK-[A-Z0-9]{8}$/', $providerTrackingNumber);

        $shipment = app(ShipmentRepositoryInterface::class)->findById($shipmentId, $tenantA);
        $this->assertSame('mock', $shipment->providerName());
        $this->assertSame($providerTrackingNumber, $shipment->providerTrackingNumber());

        // Step 4: sync tracking — 2 new events, Shipment status becomes in_transit.
        $sync = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.tracking.sync',
            'input' => ['tracking_number' => $shipment->trackingNumber()->value()],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $sync->assertStatus(200);
        $this->assertSame(2, $sync->json('data.synced_count'));
        $this->assertCount(2, $sync->json('data.events'));

        $shipmentAfterSync = app(ShipmentRepositoryInterface::class)->findById($shipmentId, $tenantA);
        $this->assertSame('in_transit', $shipmentAfterSync->status()->value);

        // Step 4b: syncing again is idempotent — no new events, status unchanged.
        $resync = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.tracking.sync',
            'input' => ['tracking_number' => $shipment->trackingNumber()->value()],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $resync->assertStatus(200);
        $this->assertSame(0, $resync->json('data.synced_count'));

        // Step 5: a simulated provider failure -> INTERNAL_ERROR (same
        // untyped-500 shape WooCommerceApiException's own test already
        // establishes) — re-register 'mock' with a failing client, same
        // "rebinding after boot() has no effect" technique
        // SyncWooCommerceProductsTest already uses.
        app(ShippingProviderRegistry::class)->register(
            'mock',
            new MockShippingProviderAdapter(new MockShippingHttpClient(simulateFailure: true)),
        );

        $failedRates = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.provider.rates',
            'input' => [
                'weight_grams' => 1000,
                'destination' => ['street' => '1 Elm St', 'city' => 'Springfield', 'country' => 'US'],
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $failedRates->assertStatus(500);
        $failedRates->assertJsonPath('error.code', 'INTERNAL_ERROR');

        // Step 6: Tenant B cannot fulfill/sync Tenant A's Shipment.
        [, , $tokenB] = $this->registerAgentWithPermissions(['shipping.providers.create', 'shipping.providers.sync']);

        $crossTenantFulfill = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.provider.fulfill',
            'input' => ['shipment_id' => $shipmentId],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantFulfill->assertStatus(404);
        $crossTenantFulfill->assertJsonPath('error.code', 'NOT_FOUND');

        $crossTenantSync = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.tracking.sync',
            'input' => ['tracking_number' => $shipment->trackingNumber()->value()],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantSync->assertStatus(404);
        $crossTenantSync->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_providerRates_withUnregisteredProviderName_returnsNotFound(): void
    {
        $this->seed(ShippingCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions(['shipping.providers.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.provider.rates',
            'input' => [
                'provider' => 'usps',
                'weight_grams' => 1000,
                'destination' => ['street' => '1 Elm St', 'city' => 'Springfield', 'country' => 'US'],
            ],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_providerFulfill_withoutPermission_returnsForbidden(): void
    {
        $this->seed(ShippingCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.provider.fulfill',
            'input' => ['shipment_id' => 1],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Ops Operator', 'ops-operator-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }
}
