<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\ValueObjects\OrderNumber;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OrderNumberTest extends TestCase
{
    public function test_generate_producesTheDocumentedFormat(): void
    {
        $orderNumber = OrderNumber::generate(new DateTimeImmutable('2026-07-30'), 42);

        $this->assertSame('ORD-20260730-00042', $orderNumber->value());
    }

    public function test_construct_withValidFormat_isAccepted(): void
    {
        $orderNumber = new OrderNumber('ORD-20260730-00001');

        $this->assertSame('ORD-20260730-00001', (string) $orderNumber);
    }

    public function test_construct_withInvalidFormat_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrderNumber('ORDER-2026-1');
    }

    public function test_equals_withSameValue_returnsTrue(): void
    {
        $a = new OrderNumber('ORD-20260730-00001');
        $b = new OrderNumber('ORD-20260730-00001');

        $this->assertTrue($a->equals($b));
    }
}
