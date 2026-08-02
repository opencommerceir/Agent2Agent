<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Exceptions\InvalidSKUException;
use App\Modules\Commerce\Domain\ValueObjects\SKU;
use App\Modules\Commerce\Domain\ValueObjects\VariantSKU;
use PHPUnit\Framework\TestCase;

class VariantSKUTest extends TestCase
{
    public function test_construct_withValidFormat_normalizesToUppercase(): void
    {
        $sku = new VariantSKU('tshirt-red-l');

        $this->assertSame('TSHIRT-RED-L', $sku->value());
    }

    public function test_construct_withInvalidCharacters_throwsInvalidSKUException(): void
    {
        $this->expectException(InvalidSKUException::class);

        new VariantSKU('sku with spaces');
    }

    public function test_generate_joinsParentSkuAndAttributeValuesWithHyphens(): void
    {
        $parentSku = new SKU('TSHIRT');

        $variantSku = VariantSKU::generate($parentSku, ['Red', 'L']);

        $this->assertSame('TSHIRT-RED-L', $variantSku->value());
    }

    public function test_generate_uppercasesEachAttributeValue(): void
    {
        $parentSku = new SKU('TSHIRT');

        $variantSku = VariantSKU::generate($parentSku, ['red', 'l']);

        $this->assertSame('TSHIRT-RED-L', $variantSku->value());
    }

    public function test_equals_withSameNormalizedValue_returnsTrue(): void
    {
        $a = new VariantSKU('tshirt-red-l');
        $b = new VariantSKU('TSHIRT-RED-L');

        $this->assertTrue($a->equals($b));
    }

    public function test_toString_returnsNormalizedValue(): void
    {
        $sku = new VariantSKU('tshirt-red-l');

        $this->assertSame('TSHIRT-RED-L', (string) $sku);
    }
}
