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
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The literal 10-step end-to-end scenario from Phase 5, Stage 2's own
 * request (§7.22), driven entirely through MCP: 3 Warehouses (Tehran,
 * Isfahan, Shiraz) with real coordinates -> per-warehouse stock (10/5/0)
 * for one Product -> a customer near Isfahan finds Isfahan as the
 * nearest Warehouse with enough stock -> a Transfer from Tehran to
 * Isfahan is requested, approved (reserving at the source), and
 * completed (moving the stock for real) -> the resulting Inventory
 * numbers are verified at both Warehouses -> an over-large Transfer is
 * rejected with a 409 -> tenant isolation is confirmed -> and, last, the
 * pre-existing non-warehouse-scoped Cart/Order flow is proven completely
 * unaffected (Backward Compatibility, the explicit requirement this
 * stage's own request called out).
 */
class WarehouseCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private const TEHRAN = ['latitude' => 35.6892, 'longitude' => 51.3890, 'address' => 'Tehran, Iran'];
    private const ISFAHAN = ['latitude' => 32.6546, 'longitude' => 51.6680, 'address' => 'Isfahan, Iran'];
    private const SHIRAZ = ['latitude' => 29.5918, 'longitude' => 52.5836, 'address' => 'Shiraz, Iran'];

    public function test_fullMultiWarehouseLifecycle_fromWarehouseCreationToCompletedTransfer(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantId, $token] = $this->registerAgentWithPermissions([
            'commerce.warehouses.manage', 'commerce.warehouses.read',
            'commerce.transfers.manage',
            'commerce.cart.manage', 'commerce.cart.read',
            'commerce.orders.create',
        ]);

        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        // Step 1: create 3 Warehouses.
        $tehranId = $this->createWarehouse($token, 'WH-TEHR1', 'Tehran Main', self::TEHRAN);
        $isfahanId = $this->createWarehouse($token, 'WH-ISFH1', 'Isfahan Branch', self::ISFAHAN);
        $shirazId = $this->createWarehouse($token, 'WH-SHRZ1', 'Shiraz Branch', self::SHIRAZ);

        $listResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.warehouse.list',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);
        $listResponse->assertStatus(200);
        $this->assertCount(3, $listResponse->json('data.warehouses'));

        // Step 2: set per-warehouse stock (Tehran: 10, Isfahan: 5, Shiraz: 0).
        // No MCP capability provisions initial warehouse stock (deliberate,
        // see GetWarehouseStockAction's own docblock) — seeded directly,
        // the same way every other stage's own E2E test seeds Inventory.
        $inventories = app(InventoryRepositoryInterface::class);
        $inventories->save(Inventory::stock($tenantId, $product->id, 10, null, $tehranId));
        $inventories->save(Inventory::stock($tenantId, $product->id, 5, null, $isfahanId));
        $inventories->save(Inventory::stock($tenantId, $product->id, 0, null, $shirazId));

        // Step 3: a customer near Isfahan finds Isfahan as the nearest
        // Warehouse that can fulfil 5 units (Tehran is farther; Shiraz has
        // zero stock and must be skipped regardless of distance).
        $nearestResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.warehouse.nearest',
            'input' => [
                'product_id' => $product->id,
                'customer_latitude' => self::ISFAHAN['latitude'],
                'customer_longitude' => self::ISFAHAN['longitude'],
                'required_quantity' => 5,
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $nearestResponse->assertStatus(200);
        $nearestResponse->assertJsonPath('data.warehouse.id', $isfahanId);

        // Step 4: request a Transfer of 5 units from Tehran to Isfahan.
        $requestResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.transfer.request',
            'input' => [
                'source_warehouse_id' => $tehranId,
                'destination_warehouse_id' => $isfahanId,
                'items' => [
                    ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5],
                ],
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $requestResponse->assertStatus(200);
        $requestResponse->assertJsonPath('data.transfer.status', 'pending');
        $transferId = $requestResponse->json('data.transfer.id');

        // Step 5: approve — reserves the 5 units at Tehran.
        $approveResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.transfer.approve',
            'input' => ['transfer_id' => $transferId],
        ], ['Authorization' => "Bearer {$token}"]);
        $approveResponse->assertStatus(200);
        $approveResponse->assertJsonPath('data.transfer.status', 'approved');

        $tehranStockAfterApprove = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.warehouse.stock',
            'input' => ['warehouse_id' => $tehranId, 'product_id' => $product->id],
        ], ['Authorization' => "Bearer {$token}"]);
        $tehranStockAfterApprove->assertJsonPath('data.quantityOnHand', 10);
        $tehranStockAfterApprove->assertJsonPath('data.quantityReserved', 5);
        $tehranStockAfterApprove->assertJsonPath('data.quantityAvailable', 5);

        // Step 6: complete — moves the stock for real.
        $completeResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.transfer.complete',
            'input' => ['transfer_id' => $transferId],
        ], ['Authorization' => "Bearer {$token}"]);
        $completeResponse->assertStatus(200);
        $completeResponse->assertJsonPath('data.transfer.status', 'completed');

        // Step 7: verify Inventory at both Warehouses.
        $tehranStockAfterComplete = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.warehouse.stock',
            'input' => ['warehouse_id' => $tehranId, 'product_id' => $product->id],
        ], ['Authorization' => "Bearer {$token}"]);
        $tehranStockAfterComplete->assertJsonPath('data.quantityOnHand', 5);
        $tehranStockAfterComplete->assertJsonPath('data.quantityReserved', 0);

        $isfahanStockAfterComplete = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.warehouse.stock',
            'input' => ['warehouse_id' => $isfahanId, 'product_id' => $product->id],
        ], ['Authorization' => "Bearer {$token}"]);
        $isfahanStockAfterComplete->assertJsonPath('data.quantityOnHand', 10);

        // Step 8: a transfer requesting more than Shiraz's own 0 units on
        // hand fails at Approve time with a 409.
        $overLargeRequest = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.transfer.request',
            'input' => [
                'source_warehouse_id' => $shirazId,
                'destination_warehouse_id' => $tehranId,
                'items' => [
                    ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
                ],
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $overLargeTransferId = $overLargeRequest->json('data.transfer.id');

        $overLargeApprove = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.transfer.approve',
            'input' => ['transfer_id' => $overLargeTransferId],
        ], ['Authorization' => "Bearer {$token}"]);
        $overLargeApprove->assertStatus(409);
        $overLargeApprove->assertJsonPath('error.code', 'CONFLICT');

        // Step 9: tenant isolation — a second tenant's Agent can never see
        // the first tenant's Warehouse.
        [, $tokenB] = $this->registerAgentWithPermissions(['commerce.warehouses.read']);
        $crossTenantGet = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.warehouse.get',
            'input' => ['warehouse_id' => $tehranId],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantGet->assertStatus(404);
        $crossTenantGet->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 10: Backward Compatibility — the pre-existing,
        // non-warehouse-scoped Cart/Order flow (warehouse_id always null)
        // is completely unaffected by any of the per-warehouse Inventory
        // rows created above; they live in entirely separate rows.
        $inventories->save(Inventory::stock($tenantId, $product->id, 20));

        $addToCartResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $product->id, 'quantity' => 3],
        ], ['Authorization' => "Bearer {$token}"]);
        $addToCartResponse->assertStatus(200);
        $cartId = $addToCartResponse->json('data.cart.id');

        $placeOrderResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.order.place',
            'input' => ['cart_id' => $cartId],
        ], ['Authorization' => "Bearer {$token}"]);
        $placeOrderResponse->assertStatus(200);

        $defaultInventory = $inventories->findByProduct($product->id, $tenantId);
        $this->assertSame(17, $defaultInventory->quantityOnHand()); // 20 - 3, untouched by any warehouse transfer above
    }

    private function createWarehouse(string $token, string $code, string $name, array $location): int
    {
        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.warehouse.create',
            'input' => [
                'code' => $code,
                'name' => $name,
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'address' => $location['address'],
            ],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);

        return $response->json('data.warehouse.id');
    }

    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Warehouse Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Warehouse Operator', 'warehouse-operator-'.uniqid());

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
