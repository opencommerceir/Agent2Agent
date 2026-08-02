<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Warehouse;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseCode;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;
use PHPUnit\Framework\TestCase;

class WarehouseTest extends TestCase
{
    public function test_create_startsActive(): void
    {
        $warehouse = Warehouse::create(1, new WarehouseCode('WH-TEHR1'), 'Tehran Main', new WarehouseLocation(35.6892, 51.3890, 'Tehran, Iran'));

        $this->assertTrue($warehouse->isActive());
        $this->assertSame('Tehran Main', $warehouse->name());
    }

    public function test_update_changesNameAndLocation_leavesCodeUntouched(): void
    {
        $warehouse = Warehouse::create(1, new WarehouseCode('WH-TEHR1'), 'Tehran Main', new WarehouseLocation(35.6892, 51.3890, 'Tehran, Iran'));

        $warehouse->update('Tehran Central', new WarehouseLocation(35.7000, 51.4000, 'New address'));

        $this->assertSame('Tehran Central', $warehouse->name());
        $this->assertSame('New address', $warehouse->location()->address);
        $this->assertSame('WH-TEHR1', $warehouse->code()->value());
    }

    public function test_deactivate_thenActivate_togglesIsActive(): void
    {
        $warehouse = Warehouse::create(1, new WarehouseCode('WH-TEHR1'), 'Tehran Main', new WarehouseLocation(35.6892, 51.3890, 'Tehran, Iran'));

        $warehouse->deactivate();
        $this->assertFalse($warehouse->isActive());

        $warehouse->activate();
        $this->assertTrue($warehouse->isActive());
    }
}
