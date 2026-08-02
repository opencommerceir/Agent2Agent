<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database.
 */
class InventoryTest extends TestCase
{
    public function test_stock_startsWithZeroReservedAndFullyAvailable(): void
    {
        $inventory = Inventory::stock(1, 100, 5);

        $this->assertSame(5, $inventory->quantityOnHand());
        $this->assertSame(0, $inventory->quantityReserved());
        $this->assertSame(5, $inventory->available());
    }

    public function test_reserve_withinAvailableStock_reducesAvailable(): void
    {
        $inventory = Inventory::stock(1, 100, 5);

        $inventory->reserve(new Quantity(3));

        $this->assertSame(3, $inventory->quantityReserved());
        $this->assertSame(2, $inventory->available());
    }

    public function test_reserve_beyondAvailableStock_throwsInsufficientInventoryException(): void
    {
        $inventory = Inventory::stock(1, 100, 5);
        $inventory->reserve(new Quantity(3));

        $this->expectException(InsufficientInventoryException::class);

        $inventory->reserve(new Quantity(3)); // only 2 left available
    }

    public function test_release_reducesReservedAndRestoresAvailability(): void
    {
        $inventory = Inventory::stock(1, 100, 5);
        $inventory->reserve(new Quantity(3));

        $inventory->release(new Quantity(2));

        $this->assertSame(1, $inventory->quantityReserved());
        $this->assertSame(4, $inventory->available());
    }

    public function test_release_moreThanReserved_clampsAtZeroRatherThanGoingNegative(): void
    {
        $inventory = Inventory::stock(1, 100, 5);
        $inventory->reserve(new Quantity(2));

        $inventory->release(new Quantity(10));

        $this->assertSame(0, $inventory->quantityReserved());
        $this->assertSame(5, $inventory->available());
    }

    public function test_stock_withWarehouseId_carriesIt(): void
    {
        $inventory = Inventory::stock(1, 100, 5, null, 7);

        $this->assertSame(7, $inventory->warehouseId());
    }

    public function test_stock_withoutWarehouseId_defaultsToNull(): void
    {
        $inventory = Inventory::stock(1, 100, 5);

        $this->assertNull($inventory->warehouseId());
    }

    public function test_receiveStock_increasesQuantityOnHandWithoutTouchingReserved(): void
    {
        $inventory = Inventory::stock(1, 100, 5, null, 7);
        $inventory->reserve(new Quantity(2));

        $inventory->receiveStock(10);

        $this->assertSame(15, $inventory->quantityOnHand());
        $this->assertSame(2, $inventory->quantityReserved());
        $this->assertSame(13, $inventory->available());
    }

    public function test_receiveStock_negativeQuantity_clampsAtCurrentOnHand(): void
    {
        $inventory = Inventory::stock(1, 100, 5);

        $inventory->receiveStock(-3);

        $this->assertSame(5, $inventory->quantityOnHand());
    }
}
