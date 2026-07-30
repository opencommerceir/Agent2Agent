<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\UCP\UCPProduct;
use PHPUnit\Framework\TestCase;

class UCPProductTest extends TestCase
{
    public function test_construct_withValidData_setsAllProperties(): void
    {
        $product = new UCPProduct(
            externalId: 'ext-1',
            sourceSystem: 'mock',
            sku: 'SKU-1',
            name: 'Widget',
            description: 'A widget.',
            priceAmount: 500,
            priceCurrency: 'USD',
            categoryIds: ['tools'],
        );

        $this->assertSame('ext-1', $product->externalId);
        $this->assertSame(500, $product->priceAmount);
        $this->assertSame(['tools'], $product->categoryIds);
        $this->assertTrue($product->isAvailable);
    }

    public function test_toArray_returnsAllFieldsAsArray(): void
    {
        $product = new UCPProduct('ext-1', 'mock', 'SKU-1', 'Widget', null, 500, 'USD');

        $array = $product->toArray();

        $this->assertSame('ext-1', $array['externalId']);
        $this->assertSame(500, $array['priceAmount']);
        $this->assertArrayHasKey('attributes', $array);
        $this->assertArrayHasKey('isAvailable', $array);
    }

    public function test_fromArray_thenToArray_roundTripsToEquivalentObject(): void
    {
        $original = new UCPProduct(
            externalId: 'ext-2',
            sourceSystem: 'mock',
            sku: 'SKU-2',
            name: 'Gadget',
            description: 'desc',
            priceAmount: 999,
            priceCurrency: 'EUR',
            categoryIds: ['electronics'],
            isAvailable: true,
            attributes: ['color' => 'red'],
        );

        $rebuilt = UCPProduct::fromArray($original->toArray());

        $this->assertEquals($original, $rebuilt);
    }

    public function test_fromArray_withMissingOptionalFields_usesDefaults(): void
    {
        $product = UCPProduct::fromArray([
            'externalId' => 'ext-3',
            'sourceSystem' => 'mock',
            'sku' => 'SKU-3',
            'name' => 'Bare Minimum',
            'priceAmount' => 100,
            'priceCurrency' => 'USD',
        ]);

        $this->assertTrue($product->isAvailable);
        $this->assertSame([], $product->categoryIds);
        $this->assertSame([], $product->attributes);
        $this->assertNull($product->description);
    }
}
