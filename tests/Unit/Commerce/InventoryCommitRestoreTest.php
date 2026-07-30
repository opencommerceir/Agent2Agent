<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

/**
 * Covers Inventory's two-phase model added for Order Management:
 * commit() (Cart reservation -> actual stock reduction on Order
 * placement) and restore() (Order cancellation -> stock back on hand).
 * reserve()/release() are already covered by InventoryTest (Cart stage).
 */
class InventoryCommitRestoreTest extends TestCase
{
    public function test_commit_reducesOnHandAndLiftsTheMatchingReservation(): void
    {
        $inventory = Inventory::stock(1, 100, 10);
        $inventory->reserve(new Quantity(3));

        $inventory->commit(new Quantity(3));

        $this->assertSame(7, $inventory->quantityOnHand());
        $this->assertSame(0, $inventory->quantityReserved());
        $this->assertSame(7, $inventory->available());
    }

    public function test_restore_putsStockDirectlyBackOnHandWithoutReReserving(): void
    {
        $inventory = Inventory::stock(1, 100, 10);
        $inventory->reserve(new Quantity(3));
        $inventory->commit(new Quantity(3));

        $inventory->restore(new Quantity(3));

        $this->assertSame(10, $inventory->quantityOnHand());
        $this->assertSame(0, $inventory->quantityReserved());
        $this->assertSame(10, $inventory->available());
    }
}
