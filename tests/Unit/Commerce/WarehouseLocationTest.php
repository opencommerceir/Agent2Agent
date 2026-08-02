<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WarehouseLocationTest extends TestCase
{
    public function test_construct_withValidCoordinates_succeeds(): void
    {
        $location = new WarehouseLocation(35.6892, 51.3890, 'Tehran, Iran');

        $this->assertSame(35.6892, $location->latitude);
        $this->assertSame(51.3890, $location->longitude);
        $this->assertSame('Tehran, Iran', $location->address);
    }

    public function test_construct_withOutOfRangeLatitude_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WarehouseLocation(91.0, 51.3890, 'Tehran, Iran');
    }

    public function test_construct_withOutOfRangeLongitude_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WarehouseLocation(35.6892, 181.0, 'Tehran, Iran');
    }

    public function test_construct_withEmptyAddress_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WarehouseLocation(35.6892, 51.3890, '  ');
    }

    public function test_fromArray_andToArray_roundTrip(): void
    {
        $data = ['latitude' => 32.6546, 'longitude' => 51.6680, 'address' => 'Isfahan, Iran'];

        $location = WarehouseLocation::fromArray($data);

        $this->assertSame($data, $location->toArray());
    }
}
