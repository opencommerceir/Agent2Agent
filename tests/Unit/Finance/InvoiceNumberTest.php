<?php

namespace Tests\Unit\Finance;

use App\Modules\Finance\Domain\ValueObjects\InvoiceNumber;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InvoiceNumberTest extends TestCase
{
    public function test_generate_producesExpectedFormat(): void
    {
        $number = InvoiceNumber::generate(new DateTimeImmutable('2026-07-31'), 42);

        $this->assertSame('INV-20260731-00042', $number->value());
    }

    public function test_construct_withValidFormat_succeeds(): void
    {
        $number = new InvoiceNumber('INV-20260731-00001');

        $this->assertSame('INV-20260731-00001', $number->value());
    }

    public function test_construct_withInvalidFormat_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InvoiceNumber('ORD-20260731-00001');
    }
}
