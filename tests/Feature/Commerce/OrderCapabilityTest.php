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
use App\Modules\Commerce\Application\Actions\CancelOrderAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full stage-3 scenario: Product with limited stock -> cart.add ->
 * order.place (inventory committed) -> order.get -> cross-tenant
 * isolation on order.get -> order.list scoped to the caller's own tenant.
 *
 * `commerce.order.cancel` is not part of this stage's requested
 * Capability Registry wiring (only place/get/list are — see
 * CommerceCapabilities' definitions()), so cancellation here goes
 * through CancelOrderAction directly, same as commerce.cart.remove in
 * the previous stage.
 */
class OrderCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_placeThenGet_thenIsolatedFromOtherTenant_thenListScopedToTenant(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantA, $tokenA] = $this->registerAgentWithPermissions([
            'commerce.cart.manage', 'commerce.orders.create', 'commerce.orders.read',
        ]);
        [, $tokenB] = $this->registerAgentWithPermissions(['commerce.orders.read']);

        $product = app(CreateProductAction::class)->execute($tenantA, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $product->id, 10));

        // Step 1: add 3 to cart.
        $addResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $product->id, 'quantity' => 3],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $addResponse->assertStatus(200);
        $cartId = $addResponse->json('data.cart.id');

        // Step 2: place the order — inventory should commit from 10 to 7.
        $placeResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.order.place',
            'input' => ['cart_id' => $cartId],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $placeResponse->assertStatus(200);
        $placeResponse->assertJsonPath('data.order.status', 'confirmed');
        $orderId = $placeResponse->json('data.order.id');

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenantA);
        $this->assertSame(7, $inventory->quantityOnHand());

        // Step 3: get the order back and confirm its contents.
        $getResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.order.get',
            'input' => ['order_id' => $orderId],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $getResponse->assertStatus(200);
        $getResponse->assertJsonPath('data.order.items.0.quantity', 3);
        $getResponse->assertJsonPath('data.order.subtotalAmount', 5997);

        // Step 4: a different tenant's Agent must never see this Order.
        $crossTenantResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.order.get',
            'input' => ['order_id' => $orderId],
        ], ['Authorization' => "Bearer {$tokenB}"]);

        $crossTenantResponse->assertStatus(404);
        $crossTenantResponse->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 5: cancel (Action-level — not an MCP capability this stage) restores inventory to 10.
        app(CancelOrderAction::class)->execute($orderId, $tenantA);
        $inventoryAfterCancel = app(InventoryRepositoryInterface::class)->findByProduct($product->id, $tenantA);
        $this->assertSame(10, $inventoryAfterCancel->quantityOnHand());

        // Step 6: list must only ever show Tenant A's own orders.
        $listResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.order.list',
            'input' => [],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $listResponse->assertStatus(200);
        $orderIds = collect($listResponse->json('data.orders'))->pluck('id');
        $this->assertTrue($orderIds->contains($orderId));
        $this->assertCount(1, $orderIds);
    }

    public function test_place_withoutPermission_returnsForbidden(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        [, $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.order.place',
            'input' => ['cart_id' => 1],
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
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Shopper', 'shopper-'.uniqid());

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
