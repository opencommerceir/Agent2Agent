<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\ValueObjects\WooCommerceProductId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WooCommerceProductIdTest extends TestCase
{
    public function test_construct_withPositiveInteger_setsValue(): void
    {
        $id = new WooCommerceProductId(123);

        $this->assertSame(123, $id->value());
        $this->assertSame('123', (string) $id);
    }

    public function test_construct_withZero_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WooCommerceProductId(0);
    }

    public function test_construct_withNegativeInteger_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WooCommerceProductId(-5);
    }

    public function test_equals_withSameValue_returnsTrue(): void
    {
        $a = new WooCommerceProductId(42);
        $b = new WooCommerceProductId(42);

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_withDifferentValue_returnsFalse(): void
    {
        $a = new WooCommerceProductId(42);
        $b = new WooCommerceProductId(43);

        $this->assertFalse($a->equals($b));
    }
}
