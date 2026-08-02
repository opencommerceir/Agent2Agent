<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Exceptions\InvalidWarehouseCodeException;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseCode;
use PHPUnit\Framework\TestCase;

class WarehouseCodeTest extends TestCase
{
    public function test_construct_withValidFormat_normalizesToUppercase(): void
    {
        $code = new WarehouseCode('wh-tehr1');

        $this->assertSame('WH-TEHR1', $code->value());
    }

    public function test_construct_withoutPrefix_throwsInvalidWarehouseCodeException(): void
    {
        $this->expectException(InvalidWarehouseCodeException::class);

        new WarehouseCode('TEHR1');
    }

    public function test_construct_withWrongLength_throwsInvalidWarehouseCodeException(): void
    {
        $this->expectException(InvalidWarehouseCodeException::class);

        new WarehouseCode('WH-TEHRAN1');
    }

    public function test_equals_withSameNormalizedValue_returnsTrue(): void
    {
        $a = new WarehouseCode('wh-tehr1');
        $b = new WarehouseCode('WH-TEHR1');

        $this->assertTrue($a->equals($b));
    }

    public function test_toString_returnsNormalizedValue(): void
    {
        $code = new WarehouseCode('wh-tehr1');

        $this->assertSame('WH-TEHR1', (string) $code);
    }
}
