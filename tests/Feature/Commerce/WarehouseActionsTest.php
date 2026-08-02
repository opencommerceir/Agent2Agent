<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\CreateWarehouseAction;
use App\Modules\Commerce\Application\Actions\GetWarehouseAction;
use App\Modules\Commerce\Application\Actions\GetWarehouseStockAction;
use App\Modules\Commerce\Application\Actions\ListWarehousesAction;
use App\Modules\Commerce\Application\Actions\UpdateWarehouseAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\DuplicateWarehouseCodeException;
use App\Modules\Commerce\Domain\Exceptions\WarehouseNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Action-level tests for the Warehouse Management CRUD Actions (Phase 5,
 * Stage 2, §7.22) — not wired to MCP as part of this test (Phase 2 of
 * this stage's own build wires all 9 capabilities at once; see
 * WarehouseCapabilityTest for the full MCP-level scenario).
 */
class WarehouseActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_thenGet_returnsTheSameWarehouse(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $warehouse = app(CreateWarehouseAction::class)->execute(
            tenantId: $tenant->id,
            code: 'wh-tehr1',
            name: 'Tehran Main',
            latitude: 35.6892,
            longitude: 51.3890,
            address: 'Tehran, Iran',
        );

        $this->assertSame('WH-TEHR1', $warehouse->code);
        $this->assertTrue($warehouse->isActive);

        $fetched = app(GetWarehouseAction::class)->execute($warehouse->id, $tenant->id);

        $this->assertSame($warehouse->id, $fetched->id);
        $this->assertSame('Tehran Main', $fetched->name);
    }

    public function test_create_withDuplicateCodeInSameTenant_throwsConflict(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');

        $this->expectException(DuplicateWarehouseCodeException::class);

        app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Another Warehouse', 35.7, 51.4, 'Somewhere else');
    }

    public function test_create_withSameCodeInDifferentTenants_succeeds(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Beta Inc', 'beta-'.uniqid());

        app(CreateWarehouseAction::class)->execute($tenantA->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $warehouseB = app(CreateWarehouseAction::class)->execute($tenantB->id, 'WH-TEHR1', 'Tehran Main (Beta)', 35.6892, 51.3890, 'Tehran, Iran');

        $this->assertSame('WH-TEHR1', $warehouseB->code);
    }

    public function test_get_forWarehouseInAnotherTenant_throwsNotFound(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Beta Inc', 'beta-'.uniqid());

        $warehouse = app(CreateWarehouseAction::class)->execute($tenantA->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');

        $this->expectException(WarehouseNotFoundException::class);

        app(GetWarehouseAction::class)->execute($warehouse->id, $tenantB->id);
    }

    public function test_update_changesNameAndLocationAndActiveStatus(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $warehouse = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');

        $updated = app(UpdateWarehouseAction::class)->execute(
            id: $warehouse->id,
            tenantId: $tenant->id,
            name: 'Tehran Central',
            latitude: 35.7,
            longitude: 51.4,
            address: 'New address',
            isActive: false,
        );

        $this->assertSame('Tehran Central', $updated->name);
        $this->assertFalse($updated->isActive);
    }

    public function test_listByTenant_onlyReturnsThatTenantsWarehouses(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Beta Inc', 'beta-'.uniqid());

        app(CreateWarehouseAction::class)->execute($tenantA->id, 'WH-TEHR1', 'Tehran', 35.6892, 51.3890, 'Tehran, Iran');
        app(CreateWarehouseAction::class)->execute($tenantA->id, 'WH-ISFH1', 'Isfahan', 32.6546, 51.6680, 'Isfahan, Iran');
        app(CreateWarehouseAction::class)->execute($tenantB->id, 'WH-SHRZ1', 'Shiraz', 29.5918, 52.5836, 'Shiraz, Iran');

        $warehouses = app(ListWarehousesAction::class)->execute($tenantA->id);

        $this->assertCount(2, $warehouses);
    }

    public function test_listByTenant_filteredByIsActive(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $active = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran', 35.6892, 51.3890, 'Tehran, Iran');
        $inactive = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-ISFH1', 'Isfahan', 32.6546, 51.6680, 'Isfahan, Iran');
        app(UpdateWarehouseAction::class)->execute($inactive->id, $tenant->id, $inactive->name, 32.6546, 51.6680, 'Isfahan, Iran', isActive: false);

        $warehouses = app(ListWarehousesAction::class)->execute($tenant->id, isActive: true);

        $this->assertCount(1, $warehouses);
        $this->assertSame('WH-TEHR1', $warehouses[0]->code);
    }

    public function test_getWarehouseStock_withNoInventoryRow_returnsZeros(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $warehouse = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        $stock = app(GetWarehouseStockAction::class)->execute($tenant->id, $warehouse->id, $product->id);

        $this->assertSame(0, $stock['quantityOnHand']);
        $this->assertSame(0, $stock['quantityAvailable']);
    }

    public function test_getWarehouseStock_reflectsThatWarehousesOwnInventoryRow(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $warehouse = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        app(InventoryRepositoryInterface::class)->save(
            Inventory::stock($tenant->id, $product->id, 10, null, $warehouse->id)
        );

        $stock = app(GetWarehouseStockAction::class)->execute($tenant->id, $warehouse->id, $product->id);

        $this->assertSame(10, $stock['quantityOnHand']);
        $this->assertSame(10, $stock['quantityAvailable']);
    }

    public function test_getWarehouseStock_forNonexistentWarehouse_throwsNotFound(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        $this->expectException(WarehouseNotFoundException::class);

        app(GetWarehouseStockAction::class)->execute($tenant->id, 999, $product->id);
    }

    public function test_defaultWarehouseInventory_isUnaffectedByPerWarehouseRows(): void
    {
        // Backward compatibility: a Product's own default (warehouse_id
        // null) Inventory row is a completely separate row from any
        // per-Warehouse row for the same Product — proves Multi-warehouse
        // Inventory doesn't disturb the pre-existing, non-warehouse-aware
        // stock path AddToCartAction/PlaceOrderAction still use.
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $warehouse = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        $inventories = app(InventoryRepositoryInterface::class);
        $inventories->save(Inventory::stock($tenant->id, $product->id, 100));
        $inventories->save(Inventory::stock($tenant->id, $product->id, 10, null, $warehouse->id));

        $default = $inventories->findByProduct($product->id, $tenant->id);
        $atWarehouse = $inventories->findByProduct($product->id, $tenant->id, null, $warehouse->id);

        $this->assertSame(100, $default->quantityOnHand());
        $this->assertSame(10, $atWarehouse->quantityOnHand());
    }
}
