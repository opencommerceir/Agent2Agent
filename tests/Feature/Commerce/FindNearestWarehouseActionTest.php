<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\CreateWarehouseAction;
use App\Modules\Commerce\Application\Actions\FindNearestWarehouseAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-level tests for FindNearestWarehouseAction (Phase 5, Stage 2 —
 * Multi-warehouse Inventory, §7.22) — the 3-warehouse scenario this
 * stage's own spec names: Tehran/Isfahan/Shiraz with real coordinates and
 * differing stock levels for one Product.
 */
class FindNearestWarehouseActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returnsNearestWarehouseWithSufficientStock(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $tehran = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $isfahan = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-ISFH1', 'Isfahan Main', 32.6546, 51.6680, 'Isfahan, Iran');
        $shiraz = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-SHRZ1', 'Shiraz Main', 29.5918, 52.5836, 'Shiraz, Iran');

        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        $inventories = app(InventoryRepositoryInterface::class);
        $inventories->save(Inventory::stock($tenant->id, $product->id, 10, null, $tehran->id));
        $inventories->save(Inventory::stock($tenant->id, $product->id, 5, null, $isfahan->id));
        $inventories->save(Inventory::stock($tenant->id, $product->id, 0, null, $shiraz->id));

        // A customer located near Isfahan.
        $result = app(FindNearestWarehouseAction::class)->execute(
            tenantId: $tenant->id,
            productId: $product->id,
            customerLatitude: 32.6000,
            customerLongitude: 51.6000,
            requiredQuantity: 5,
        );

        $this->assertNotNull($result);
        $this->assertSame('WH-ISFH1', $result->code);
    }

    public function test_execute_returnsNull_whenNoWarehouseHasEnoughStock(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $tehran = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $isfahan = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-ISFH1', 'Isfahan Main', 32.6546, 51.6680, 'Isfahan, Iran');
        $shiraz = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-SHRZ1', 'Shiraz Main', 29.5918, 52.5836, 'Shiraz, Iran');

        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        $inventories = app(InventoryRepositoryInterface::class);
        $inventories->save(Inventory::stock($tenant->id, $product->id, 10, null, $tehran->id));
        $inventories->save(Inventory::stock($tenant->id, $product->id, 5, null, $isfahan->id));
        $inventories->save(Inventory::stock($tenant->id, $product->id, 0, null, $shiraz->id));

        $result = app(FindNearestWarehouseAction::class)->execute(
            tenantId: $tenant->id,
            productId: $product->id,
            customerLatitude: 32.6000,
            customerLongitude: 51.6000,
            requiredQuantity: 999,
        );

        $this->assertNull($result);
    }
}
