<?php

namespace Tests\Feature\Shipping;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\CreateWarehouseAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Shipping\Application\Actions\CalculateShippingRateAction;
use App\Modules\Shipping\Domain\Entities\ShippingMethod;
use App\Modules\Shipping\Domain\Repositories\ShippingMethodRepositoryInterface;
use App\Modules\Shipping\Domain\ValueObjects\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-level tests proving Shipping's own CalculateShippingRateAction
 * can, when given a customer location + Product, find the nearest
 * Commerce Warehouse with enough stock and price a distance surcharge on
 * top of the pre-existing base+weight rate (Phase 5, Stage 2 —
 * Multi-warehouse Inventory, §7.22). Also proves the pre-existing 3-arg
 * call shape is completely unaffected by this widening.
 */
class WarehouseAwareShippingRateTest extends TestCase
{
    use RefreshDatabase;

    private function createShippingMethod(int $tenantId, int $baseRate, int $ratePerKg, int $ratePerKm): int
    {
        $method = new ShippingMethod(
            id: null,
            tenantId: $tenantId,
            name: 'Standard',
            description: null,
            baseRate: Money::fromAmount($baseRate, 'USD'),
            ratePerKg: Money::fromAmount($ratePerKg, 'USD'),
            estimatedDaysMin: 2,
            estimatedDaysMax: 5,
            isActive: true,
            createdAt: new DateTimeImmutable(),
            ratePerKm: Money::fromAmount($ratePerKm, 'USD'),
        );

        $saved = app(ShippingMethodRepositoryInterface::class)->save($method);

        return $saved->id();
    }

    public function test_execute_withCustomerLocationAndProduct_addsDistanceSurchargeOnTopOfBaseRate(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $tehran = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-TEHR1', 'Tehran Main', 35.6892, 51.3890, 'Tehran, Iran');
        $isfahan = app(CreateWarehouseAction::class)->execute($tenant->id, 'WH-ISFH1', 'Isfahan Main', 32.6546, 51.6680, 'Isfahan, Iran');

        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 1999, 'USD', status: 'active');

        $inventories = app(InventoryRepositoryInterface::class);
        $inventories->save(Inventory::stock($tenant->id, $product->id, 10, null, $tehran->id));
        $inventories->save(Inventory::stock($tenant->id, $product->id, 10, null, $isfahan->id));

        $methodId = $this->createShippingMethod($tenant->id, baseRate: 500, ratePerKg: 100, ratePerKm: 50);

        $action = app(CalculateShippingRateAction::class);

        // Baseline: no distance surcharge, the pre-existing behavior.
        // 500 + (2kg * 100) = 700.
        $baseline = $action->execute($tenant->id, $methodId, 2000);
        $this->assertSame(700, $baseline->costAmount);

        // With a customer location near Isfahan + the Product, the nearest
        // qualifying Warehouse should be found and priced with a distance
        // surcharge on top of the same base+weight rate.
        $withDistance = $action->execute(
            $tenant->id,
            $methodId,
            2000,
            customerLatitude: 32.6000,
            customerLongitude: 51.6000,
            productId: $product->id,
            requiredQuantity: 5,
        );

        $this->assertGreaterThan($baseline->costAmount, $withDistance->costAmount);
    }

    public function test_execute_withOnlyOriginalThreeArgs_isIdenticalToPreExistingBehavior(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $methodId = $this->createShippingMethod($tenant->id, baseRate: 500, ratePerKg: 100, ratePerKm: 50);

        $action = app(CalculateShippingRateAction::class);

        $rate = $action->execute($tenant->id, $methodId, 2500);

        // 500 + (2.5kg * 100) = 750 — identical to ShippingRateCalculatorTest's
        // own pre-existing expectation, proving the widening changed nothing
        // for callers that don't pass the four new optional params.
        $this->assertSame(750, $rate->costAmount);
        $this->assertSame('USD', $rate->costCurrency);
        $this->assertSame(2, $rate->estimatedDaysMin);
        $this->assertSame(5, $rate->estimatedDaysMax);
    }
}
