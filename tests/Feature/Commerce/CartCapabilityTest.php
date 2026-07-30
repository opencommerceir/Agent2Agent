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
 * Exercises the exact end-to-end scenario this stage was built for: a
 * limited-stock Product, one Agent successfully reserving stock via
 * `commerce.cart.add`, a second attempt from the *same* Agent correctly
 * rejected once stock runs out, and a *different* tenant's Agent never
 * seeing the first tenant's cart via `commerce.cart.get` — the concrete
 * proof the AuthContext-carried tenantId/agentId actually enforces
 * isolation, not just that the code compiles.
 *
 * `commerce.cart.remove` was not part of this stage's requested
 * Capability Registry wiring (only cart.add / cart.get were) — removal
 * is covered at the Action level instead (RemoveFromCartTest).
 */
class CartCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CommerceCapabilitiesSeeder::class);
    }

    public function test_addThenExceedStock_thenIsolatedFromOtherTenant(): void
    {
        [$tenantA, $tokenA] = $this->registerAgentWithPermissions(['commerce.cart.manage', 'commerce.cart.read']);
        [$tenantB, $tokenB] = $this->registerAgentWithPermissions(['commerce.cart.manage', 'commerce.cart.read']);

        $product = app(CreateProductAction::class)->execute($tenantA, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $product->id, 5));

        // Step 1: Agent A adds 3 — succeeds.
        $first = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $product->id, 'quantity' => 3],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $first->assertStatus(200);
        $first->assertJsonPath('data.cart.items.0.quantity', 3);

        // Step 2: Agent A tries 3 more — only 2 left, must be rejected as a conflict, not silently oversold.
        $second = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $product->id, 'quantity' => 3],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $second->assertStatus(409);
        $second->assertJsonPath('error.code', 'CONFLICT');

        // Step 3: Agent B (different tenant) must never see Tenant A's cart.
        $bCart = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.get',
            'input' => [],
        ], ['Authorization' => "Bearer {$tokenB}"]);

        $bCart->assertStatus(200);
        $bCart->assertJsonPath('data.cart.items', []);

        // Sanity check: Agent A's own cart still shows the successful reservation.
        $aCart = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.get',
            'input' => [],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $aCart->assertStatus(200);
        $aCart->assertJsonPath('data.cart.items.0.quantity', 3);
    }

    public function test_add_withoutPermission_returnsForbidden(): void
    {
        [, $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => 1, 'quantity' => 1],
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
