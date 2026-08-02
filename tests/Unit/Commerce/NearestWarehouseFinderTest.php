<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Warehouse;
use App\Modules\Commerce\Domain\Services\NearestWarehouseFinder;
use App\Modules\Commerce\Domain\Services\WarehouseDistanceCalculator;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseCode;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;
use PHPUnit\Framework\TestCase;

class NearestWarehouseFinderTest extends TestCase
{
    private NearestWarehouseFinder $finder;

    protected function setUp(): void
    {
        $this->finder = new NearestWarehouseFinder(new WarehouseDistanceCalculator());
    }

    /** Customer location: near Isfahan. */
    private function customerLocation(): WarehouseLocation
    {
        return new WarehouseLocation(32.6000, 51.6000, 'Near Isfahan');
    }

    private function tehranWarehouse(): Warehouse
    {
        return Warehouse::create(1, new WarehouseCode('WH-TEHR1'), 'Tehran Main', new WarehouseLocation(35.6892, 51.3890, 'Tehran, Iran'));
    }

    private function isfahanWarehouse(): Warehouse
    {
        return Warehouse::create(1, new WarehouseCode('WH-ISFH1'), 'Isfahan Main', new WarehouseLocation(32.6546, 51.6680, 'Isfahan, Iran'));
    }

    private function shirazWarehouse(): Warehouse
    {
        return Warehouse::create(1, new WarehouseCode('WH-SHRZ1'), 'Shiraz Main', new WarehouseLocation(29.5918, 52.5836, 'Shiraz, Iran'));
    }

    public function test_find_picksClosestWarehouseWithEnoughStock(): void
    {
        $tehran = $this->tehranWarehouse();
        $isfahan = $this->isfahanWarehouse();

        $candidates = [
            ['warehouse' => $tehran, 'availableQuantity' => 10],
            ['warehouse' => $isfahan, 'availableQuantity' => 10],
        ];

        $result = $this->finder->find($candidates, $this->customerLocation(), 5);

        $this->assertSame($isfahan, $result);
    }

    public function test_find_skipsCloserWarehouseWithInsufficientStock(): void
    {
        $isfahan = $this->isfahanWarehouse(); // closest, but not enough stock
        $shiraz = $this->shirazWarehouse(); // farther, has enough stock

        $candidates = [
            ['warehouse' => $isfahan, 'availableQuantity' => 2],
            ['warehouse' => $shiraz, 'availableQuantity' => 10],
        ];

        $result = $this->finder->find($candidates, $this->customerLocation(), 5);

        $this->assertSame($shiraz, $result);
    }

    public function test_find_returnsNull_whenNoCandidateHasEnoughStock(): void
    {
        $tehran = $this->tehranWarehouse();
        $isfahan = $this->isfahanWarehouse();

        $candidates = [
            ['warehouse' => $tehran, 'availableQuantity' => 1],
            ['warehouse' => $isfahan, 'availableQuantity' => 2],
        ];

        $result = $this->finder->find($candidates, $this->customerLocation(), 5);

        $this->assertNull($result);
    }

    public function test_find_skipsInactiveWarehouse_evenWhenClosestWithStock(): void
    {
        $isfahan = $this->isfahanWarehouse();
        $isfahan->deactivate();

        $tehran = $this->tehranWarehouse();

        $candidates = [
            ['warehouse' => $isfahan, 'availableQuantity' => 10],
            ['warehouse' => $tehran, 'availableQuantity' => 10],
        ];

        $result = $this->finder->find($candidates, $this->customerLocation(), 5);

        $this->assertSame($tehran, $result);
    }
}
