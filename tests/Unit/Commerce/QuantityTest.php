<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Exceptions\InvalidQuantityException;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    public function test_construct_withPositiveValue_setsValue(): void
    {
        $quantity = new Quantity(3);

        $this->assertSame(3, $quantity->value());
    }

    public function test_construct_withZero_throwsInvalidQuantityException(): void
    {
        $this->expectException(InvalidQuantityException::class);

        new Quantity(0);
    }

    public function test_construct_withNegativeValue_throwsInvalidQuantityException(): void
    {
        $this->expectException(InvalidQuantityException::class);

        new Quantity(-1);
    }

    public function test_add_returnsNewQuantityWithSummedValue(): void
    {
        $result = (new Quantity(2))->add(new Quantity(3));

        $this->assertSame(5, $result->value());
    }

    public function test_equals_withSameValue_returnsTrue(): void
    {
        $this->assertTrue((new Quantity(4))->equals(new Quantity(4)));
    }
}
