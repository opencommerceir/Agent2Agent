<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\ApproveWarehouseTransferAction;
use App\Modules\Commerce\Application\Actions\CompleteWarehouseTransferAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\CreateWarehouseAction;
use App\Modules\Commerce\Application\Actions\RequestWarehouseTransferAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\InsufficientWarehouseStockException;
use App\Modules\Commerce\Domain\Exceptions\WarehouseNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\WarehouseTransferNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Action-level tests for the Warehouse Transfer workflow (Phase 5, Stage
 * 2, §7.22): Request -> Approve (reserve at source) -> Complete (commit
 * at source, receive at destination). Not wired to MCP as part of this
 * test — Actions are exercised directly, the same scope
 * WarehouseActionsTest already established for this stage's other Action
 * group.
 */
class WarehouseTransferActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_requestThenApproveThenComplete_movesStockBetweenWarehouses(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $source = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $destination = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-ISFH1', 'Isfahan', 32.6546, 51.6680, 'Isfahan, Iran');
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        $inventories = app(InventoryRepositoryInterface::class);
        $inventories->save(Inventory::stock($tenant->id, $product->id, 10, null, $source->id));

        $transfer = app(RequestWarehouseTransferAction::class)->execute(
            tenantId: $tenant->id,
            sourceWarehouseId: $source->id,
            destinationWarehouseId: $destination->id,
            requestedBy: $agentId,
            items: [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5],
            ],
        );

        $this->assertSame('pending', $transfer->status);

        $approved = app(ApproveWarehouseTransferAction::class)->execute($transfer->id, $tenant->id, $agentId);

        $this->assertSame('approved', $approved->status);

        $sourceAfterApprove = $inventories->findByProduct($product->id, $tenant->id, null, $source->id);
        $this->assertSame(5, $sourceAfterApprove->quantityReserved());
        $this->assertSame(5, $sourceAfterApprove->available());
        $this->assertSame(10, $sourceAfterApprove->quantityOnHand());

        $completed = app(CompleteWarehouseTransferAction::class)->execute($transfer->id, $tenant->id);

        $this->assertSame('completed', $completed->status);
        $this->assertNotNull($completed->completedAt);

        $sourceAfterComplete = $inventories->findByProduct($product->id, $tenant->id, null, $source->id);
        $this->assertSame(5, $sourceAfterComplete->quantityOnHand());
        $this->assertSame(0, $sourceAfterComplete->quantityReserved());

        $destinationAfterComplete = $inventories->findByProduct($product->id, $tenant->id, null, $destination->id);
        $this->assertSame(5, $destinationAfterComplete->quantityOnHand());
    }

    public function test_approve_requestingMoreThanAvailable_throwsConflict(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $source = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $destination = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-ISFH1', 'Isfahan', 32.6546, 51.6680, 'Isfahan, Iran');
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 3, null, $source->id));

        $transfer = app(RequestWarehouseTransferAction::class)->execute(
            tenantId: $tenant->id,
            sourceWarehouseId: $source->id,
            destinationWarehouseId: $destination->id,
            requestedBy: $agentId,
            items: [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5],
            ],
        );

        $this->expectException(InsufficientWarehouseStockException::class);

        app(ApproveWarehouseTransferAction::class)->execute($transfer->id, $tenant->id, $agentId);
    }

    public function test_transferCreatedUnderOneTenant_isNotVisibleToAnotherTenant(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Beta Inc', 'beta-'.uniqid());
        $agentA = $this->registerAgent($tenantA->id);

        $source = app(CreateWarehouseAction::class)->execute($tenantA->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $destination = app(CreateWarehouseAction::class)->execute($tenantA->id, 'WH-ISFH1', 'Isfahan', 32.6546, 51.6680, 'Isfahan, Iran');
        $product = app(CreateProductAction::class)->execute($tenantA->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA->id, $product->id, 10, null, $source->id));

        $transfer = app(RequestWarehouseTransferAction::class)->execute(
            tenantId: $tenantA->id,
            sourceWarehouseId: $source->id,
            destinationWarehouseId: $destination->id,
            requestedBy: $agentA,
            items: [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5],
            ],
        );

        $this->expectException(WarehouseTransferNotFoundException::class);

        app(ApproveWarehouseTransferAction::class)->execute($transfer->id, $tenantB->id, $agentA);
    }

    public function test_complete_forStillPendingTransfer_throwsIllegalTransition(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $source = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $destination = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-ISFH1', 'Isfahan', 32.6546, 51.6680, 'Isfahan, Iran');
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10, null, $source->id));

        $transfer = app(RequestWarehouseTransferAction::class)->execute(
            tenantId: $tenant->id,
            sourceWarehouseId: $source->id,
            destinationWarehouseId: $destination->id,
            requestedBy: $agentId,
            items: [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5],
            ],
        );

        $this->expectException(InvalidArgumentException::class);

        app(CompleteWarehouseTransferAction::class)->execute($transfer->id, $tenant->id);
    }

    public function test_request_againstNonexistentSourceWarehouse_throwsNotFound(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $destination = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-ISFH1', 'Isfahan', 32.6546, 51.6680, 'Isfahan, Iran');
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        $this->expectException(WarehouseNotFoundException::class);

        app(RequestWarehouseTransferAction::class)->execute(
            tenantId: $tenant->id,
            sourceWarehouseId: 999,
            destinationWarehouseId: $destination->id,
            requestedBy: $agentId,
            items: [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5],
            ],
        );
    }

    public function test_request_againstNonexistentDestinationWarehouse_throwsNotFound(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $agentId = $this->registerAgent($tenant->id);

        $source = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        $this->expectException(WarehouseNotFoundException::class);

        app(RequestWarehouseTransferAction::class)->execute(
            tenantId: $tenant->id,
            sourceWarehouseId: $source->id,
            destinationWarehouseId: 999,
            requestedBy: $agentId,
            items: [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5],
            ],
        );
    }

    private function registerAgent(int $tenantId): int
    {
        $organization = app(CreateOrganizationAction::class)->execute($tenantId, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenantId, $organization->id, 'Warehouse Assistant', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return $agent->id;
    }
}
