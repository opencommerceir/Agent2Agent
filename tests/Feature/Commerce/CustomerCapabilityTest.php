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
use App\Modules\Commerce\Application\Actions\GetCustomerOrdersAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full stage-4 scenario: create Customer -> duplicate email rejected
 * as a 409 conflict -> get Customer -> cross-tenant isolation on
 * customer.get -> an Order placed for that Customer -> customer.list
 * scoped to the caller's own tenant.
 *
 * GetCustomerOrdersAction (Customer <-> Order interaction) is exercised
 * directly, not through MCP — it wasn't part of this stage's requested
 * Capability Registry wiring (only create/get/list were), same gap
 * pattern as cart.remove and order.cancel in earlier stages.
 */
class CustomerCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_createThenGet_thenIsolatedFromOtherTenant_thenListScopedToTenant(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantA, $tokenA] = $this->registerAgentWithPermissions([
            'commerce.customers.create', 'commerce.customers.read', 'commerce.cart.manage', 'commerce.orders.create',
        ]);
        [, $tokenB] = $this->registerAgentWithPermissions(['commerce.customers.read']);

        // Step 1: create.
        $createResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.customer.create',
            'input' => ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $createResponse->assertStatus(200);
        $customerId = $createResponse->json('data.customer.id');

        // Step 2: duplicate email -> 409 CONFLICT.
        $duplicateResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.customer.create',
            'input' => ['first_name' => 'Jane', 'last_name' => 'Other', 'email' => 'jane@example.com'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $duplicateResponse->assertStatus(409);
        $duplicateResponse->assertJsonPath('error.code', 'CONFLICT');

        // Step 3: get.
        $getResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.customer.get',
            'input' => ['customer_id' => $customerId],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $getResponse->assertStatus(200);
        $getResponse->assertJsonPath('data.customer.email', 'jane@example.com');

        // Step 4: a different tenant's Agent must never see this Customer.
        $crossTenantResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.customer.get',
            'input' => ['customer_id' => $customerId],
        ], ['Authorization' => "Bearer {$tokenB}"]);

        $crossTenantResponse->assertStatus(404);
        $crossTenantResponse->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 5: place an Order linked to this Customer.
        $product = app(CreateProductAction::class)->execute($tenantA, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $product->id, 10));

        $addResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $product->id, 'quantity' => 2],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $cartId = $addResponse->json('data.cart.id');

        $placeResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.order.place',
            'input' => ['cart_id' => $cartId, 'customer_id' => $customerId],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $placeResponse->assertStatus(200);
        $placeResponse->assertJsonPath('data.order.customerId', $customerId);

        // Step 6: GetCustomerOrdersAction (Action-level — not an MCP capability this stage) sees it.
        $customerOrders = app(GetCustomerOrdersAction::class)->execute($customerId, $tenantA);
        $this->assertCount(1, $customerOrders['orders']);

        // Step 7: list must only ever show Tenant A's own customers.
        $listResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.customer.list',
            'input' => [],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $listResponse->assertStatus(200);
        $customerIds = collect($listResponse->json('data.customers'))->pluck('id');
        $this->assertTrue($customerIds->contains($customerId));
        $this->assertCount(1, $customerIds);
    }

    public function test_create_withoutPermission_returnsForbidden(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        [, $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.customer.create',
            'input' => ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: string, 2: int}
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

        return [$tenant->id, $token, $agent->id];
    }
}
