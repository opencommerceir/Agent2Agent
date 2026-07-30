<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Application\Actions\CheckInventoryAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * CheckInventoryAction depends only on InventoryRepositoryInterface — it
 * can be tested in complete isolation from Laravel and the database with
 * a Mockery fake standing in for persistence (same pattern
 * tests/Unit/MCP/AuthenticationTest.php established).
 */
class CheckInventoryActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_execute_withEnoughAvailableStock_returnsTrue(): void
    {
        $inventory = Inventory::stock(1, 100, 5);

        $inventories = Mockery::mock(InventoryRepositoryInterface::class);
        $inventories->shouldReceive('findByProduct')->once()->with(100, 1)->andReturn($inventory);

        $result = (new CheckInventoryAction($inventories))->execute(100, 1, new Quantity(3));

        $this->assertTrue($result);
    }

    public function test_execute_withNotEnoughAvailableStock_returnsFalse(): void
    {
        $inventory = Inventory::stock(1, 100, 5);
        $inventory->reserve(new Quantity(4));

        $inventories = Mockery::mock(InventoryRepositoryInterface::class);
        $inventories->shouldReceive('findByProduct')->once()->with(100, 1)->andReturn($inventory);

        $result = (new CheckInventoryAction($inventories))->execute(100, 1, new Quantity(2));

        $this->assertFalse($result);
    }

    public function test_execute_withNoInventoryRecordAtAll_returnsFalse(): void
    {
        $inventories = Mockery::mock(InventoryRepositoryInterface::class);
        $inventories->shouldReceive('findByProduct')->once()->with(100, 1)->andReturn(null);

        $result = (new CheckInventoryAction($inventories))->execute(100, 1, new Quantity(1));

        $this->assertFalse($result);
    }

    public function test_authorize_withInsufficientStock_throwsInsufficientInventoryException(): void
    {
        $inventories = Mockery::mock(InventoryRepositoryInterface::class);
        $inventories->shouldReceive('findByProduct')->once()->with(100, 1)->andReturn(null);

        $this->expectException(InsufficientInventoryException::class);

        (new CheckInventoryAction($inventories))->authorize(100, 1, new Quantity(1));
    }

    public function test_authorize_withSufficientStock_doesNotThrow(): void
    {
        $inventory = Inventory::stock(1, 100, 5);

        $inventories = Mockery::mock(InventoryRepositoryInterface::class);
        $inventories->shouldReceive('findByProduct')->once()->with(100, 1)->andReturn($inventory);

        (new CheckInventoryAction($inventories))->authorize(100, 1, new Quantity(5));

        $this->assertTrue(true);
    }
}
