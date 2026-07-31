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
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Shipping\Domain\Repositories\ShipmentRepositoryInterface;
use Database\Seeders\ShippingCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full Phase 4.1 (Shipping) scenario over real MCP HTTP requests
 * plus real Commerce Action calls: a real Order with 2 Products (2500g
 * combined) -> a real ShippingMethod -> a rate preview -> a real
 * Shipment (real tracking number, real Order.assignShipping() write-back)
 * -> a status transition -> a tracking event -> tenant isolation ->
 * status-filtered listing.
 */
class ShippingCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullShippingScenario(): void
    {
        $this->seed(ShippingCapabilitiesSeeder::class);

        [$tenantA, $agentA, $tokenA] = $this->registerAgentWithPermissions([
            'shipping.methods.create', 'shipping.methods.read', 'shipping.rates.read',
            'shipping.shipments.create', 'shipping.shipments.read', 'shipping.shipments.update',
        ]);

        // Step 2: an Order with 2 Products — 1000g x1 + 750g x2 = 2500g.
        $productA = app(CreateProductAction::class)->execute(
            $tenantA, 'Heavy Widget', 'SKU-HEAVY', 2000, 'USD', status: 'active', attributes: ['weight_grams' => 1000],
        );
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $productA->id, 100));

        $productB = app(CreateProductAction::class)->execute(
            $tenantA, 'Light Widget', 'SKU-LIGHT', 1500, 'USD', status: 'active', attributes: ['weight_grams' => 750],
        );
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $productB->id, 100));

        app(AddToCartAction::class)->execute($tenantA, MemberType::Agent, $agentA, $productA->id, 1);
        $cart = app(AddToCartAction::class)->execute($tenantA, MemberType::Agent, $agentA, $productB->id, 2);

        $order = app(PlaceOrderAction::class)->execute(tenantId: $tenantA, agentId: $agentA, cartId: $cart->id);

        // Step 1: a ShippingMethod, base_rate=500, rate_per_kg=100.
        $createMethod = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.method.create',
            'input' => [
                'name' => 'Standard Shipping',
                'base_rate' => 500,
                'rate_per_kg' => 100,
                'estimated_days_min' => 2,
                'estimated_days_max' => 5,
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $createMethod->assertStatus(200);
        $methodId = $createMethod->json('data.method.id');

        // Step 3: 500 + (2.5kg * 100) = 750.
        $calculate = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.rate.calculate',
            'input' => ['shipping_method_id' => $methodId, 'weight_grams' => 2500],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $calculate->assertStatus(200);
        $this->assertSame(750, $calculate->json('data.rate.costAmount'));
        $this->assertSame(2, $calculate->json('data.rate.estimatedDaysMin'));
        $this->assertSame(5, $calculate->json('data.rate.estimatedDaysMax'));

        // Step 4: create the Shipment from the Order.
        $createShipment = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.create',
            'input' => ['order_id' => $order->id, 'shipping_method_id' => $methodId],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $createShipment->assertStatus(200);
        $shipmentId = $createShipment->json('data.shipment.id');
        $trackingNumber = $createShipment->json('data.shipment.trackingNumber');
        $this->assertMatchesRegularExpression('/^TRK-[A-Z0-9]{8}$/', $trackingNumber);
        $this->assertSame(2500, $createShipment->json('data.shipment.weightGrams'));
        $this->assertSame(750, $createShipment->json('data.shipment.shippingCostAmount'));
        $this->assertSame('pending', $createShipment->json('data.shipment.status'));

        // Step 5: the Order itself was updated (Order::assignShipping()).
        $updatedOrder = app(OrderRepositoryInterface::class)->findById($order->id, $tenantA);
        $this->assertSame($methodId, $updatedOrder->shippingMethodId());
        $this->assertSame($shipmentId, $updatedOrder->shipmentId());
        $this->assertSame(750, $updatedOrder->shippingCost()->amount());

        // Step 6: transition to in_transit.
        $updateStatus = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.transition',
            'input' => ['shipment_id' => $shipmentId, 'status' => 'in_transit'],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $updateStatus->assertStatus(200);
        $this->assertSame('in_transit', $updateStatus->json('data.shipment.status'));
        $this->assertNotNull($updateStatus->json('data.shipment.shippedAt'));

        // Step 7: add a tracking event — does not itself change status.
        $addEvent = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.tracking.add',
            'input' => [
                'shipment_id' => $shipmentId,
                'status' => 'in_transit',
                'location' => 'Sorting Facility A',
                'description' => 'Arrived at sorting facility',
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $addEvent->assertStatus(200);
        $this->assertSame('Sorting Facility A', $addEvent->json('data.event.location'));

        // Step 8: Tenant B's Agent cannot see Tenant A's Shipment.
        [, , $tokenB] = $this->registerAgentWithPermissions(['shipping.shipments.read']);

        $crossTenant = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.get',
            'input' => ['shipment_id' => $shipmentId],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenant->assertStatus(404);
        $crossTenant->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 9: list Shipments filtered by status.
        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.list',
            'input' => ['status' => 'in_transit'],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data.shipments'));
        $this->assertSame($shipmentId, $list->json('data.shipments.0.id'));

        // Step 10: Tracking Number uniqueness — a second Shipment (a
        // second Order) gets a different tracking number, and the
        // Repository's own uniqueness check recognizes the first one as taken.
        $secondCart = app(AddToCartAction::class)->execute($tenantA, MemberType::Agent, $agentA, $productA->id, 1);
        $secondOrder = app(PlaceOrderAction::class)->execute(tenantId: $tenantA, agentId: $agentA, cartId: $secondCart->id);

        $secondShipment = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.create',
            'input' => ['order_id' => $secondOrder->id, 'shipping_method_id' => $methodId],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $secondShipment->assertStatus(200);
        $this->assertNotSame($trackingNumber, $secondShipment->json('data.shipment.trackingNumber'));

        $this->assertTrue(
            app(ShipmentRepositoryInterface::class)->trackingNumberExists($trackingNumber, $tenantA),
        );
    }

    public function test_createShipment_withInvalidStatusTransition_returnsValidationError(): void
    {
        $this->seed(ShippingCapabilitiesSeeder::class);
        [$tenantId, $agentId, $token] = $this->registerAgentWithPermissions([
            'shipping.methods.create', 'shipping.shipments.create', 'shipping.shipments.update',
        ]);

        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'SKU-1', 1000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantId, $product->id, 100));
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $product->id, 1);
        $order = app(PlaceOrderAction::class)->execute(tenantId: $tenantId, agentId: $agentId, cartId: $cart->id);

        $method = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.method.create',
            'input' => ['name' => 'Standard', 'base_rate' => 500, 'rate_per_kg' => 100, 'estimated_days_min' => 2, 'estimated_days_max' => 5],
        ], ['Authorization' => "Bearer {$token}"]);
        $methodId = $method->json('data.method.id');

        $shipment = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.create',
            'input' => ['order_id' => $order->id, 'shipping_method_id' => $methodId],
        ], ['Authorization' => "Bearer {$token}"]);
        $shipmentId = $shipment->json('data.shipment.id');

        // pending -> delivered directly is not an allowed transition.
        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.transition',
            'input' => ['shipment_id' => $shipmentId, 'status' => 'delivered'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_createShipment_forNonexistentOrder_returnsNotFound(): void
    {
        $this->seed(ShippingCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions(['shipping.methods.create', 'shipping.shipments.create']);

        $method = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.method.create',
            'input' => ['name' => 'Standard', 'base_rate' => 500, 'rate_per_kg' => 100, 'estimated_days_min' => 2, 'estimated_days_max' => 5],
        ], ['Authorization' => "Bearer {$token}"]);
        $methodId = $method->json('data.method.id');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.shipment.create',
            'input' => ['order_id' => 999999, 'shipping_method_id' => $methodId],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_createShippingMethod_withoutPermission_returnsForbidden(): void
    {
        $this->seed(ShippingCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'shipping.method.create',
            'input' => ['name' => 'X', 'base_rate' => 100, 'rate_per_kg' => 10, 'estimated_days_min' => 1, 'estimated_days_max' => 2],
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
