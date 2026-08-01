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
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression guard for the N+1 fix in EloquentOrderRepository::listByTenant()
 * (Phase 4 Stage 8, Performance Optimization, §7.20) — toEntity() always
 * reads $model->items, so listing N Orders without eager-loading `items`
 * cost 1 (for the Orders themselves) + N (one per Order's items) queries.
 * Asserts the query count stays flat between 1 Order and 4 Orders, not
 * just that the returned data is still correct — the same style
 * CheckPermissionTest's own N+1 regression test already established
 * (Tech Debt Sprint, §7.13).
 */
class OrderRepositoryEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_listByTenant_queryCountStaysConstantRegardlessOfOrderCount(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);
        [$tenantId, $token] = $this->registerAgentWithPermissions([
            'commerce.cart.manage', 'commerce.orders.create', 'commerce.orders.read',
        ]);

        $product = app(CreateProductAction::class)->execute($tenantId, 'Widget', 'WIDGET-1', 999, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantId, $product->id, 100));

        $this->placeOneOrder($token, $product->id);

        DB::enableQueryLog();
        app(OrderRepositoryInterface::class)->listByTenant($tenantId, null, 50);
        $queryCountForOneOrder = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        for ($i = 0; $i < 3; $i++) {
            $this->placeOneOrder($token, $product->id);
        }

        DB::enableQueryLog();
        $orders = app(OrderRepositoryInterface::class)->listByTenant($tenantId, null, 50);
        $queryCountForFourOrders = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(4, $orders);
        $this->assertSame($queryCountForOneOrder, $queryCountForFourOrders);
    }

    private function placeOneOrder(string $token, int $productId): void
    {
        $addResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $productId, 'quantity' => 1],
        ], ['Authorization' => "Bearer {$token}"]);

        $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.order.place',
            'input' => ['cart_id' => $addResponse->json('data.cart.id')],
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200);
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

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Shopper', 'shopper-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $permissionId = app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $token];
    }
}
