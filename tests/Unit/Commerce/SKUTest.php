<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Exceptions\InvalidSKUException;
use App\Modules\Commerce\Domain\ValueObjects\SKU;
use PHPUnit\Framework\TestCase;

class SKUTest extends TestCase
{
    public function test_construct_withValidFormat_normalizesToUppercase(): void
    {
        $sku = new SKU('abc-123');

        $this->assertSame('ABC-123', $sku->value());
    }

    public function test_construct_withTooShortValue_throwsInvalidSKUException(): void
    {
        $this->expectException(InvalidSKUException::class);

        new SKU('ab');
    }

    public function test_construct_withInvalidCharacters_throwsInvalidSKUException(): void
    {
        $this->expectException(InvalidSKUException::class);

        new SKU('sku with spaces');
    }

    public function test_equals_withSameNormalizedValue_returnsTrue(): void
    {
        $a = new SKU('sku-1');
        $b = new SKU('SKU-1');

        $this->assertTrue($a->equals($b));
    }

    public function test_toString_returnsNormalizedValue(): void
    {
        $sku = new SKU('sku-1');

        $this->assertSame('SKU-1', (string) $sku);
    }
}
