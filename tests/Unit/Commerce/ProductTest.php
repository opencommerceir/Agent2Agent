<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Product;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;
use App\Modules\Commerce\Domain\ValueObjects\SKU;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database (Domain
 * Layer Rules: no framework dependency).
 */
class ProductTest extends TestCase
{
    public function test_create_withDefaults_startsAsDraftWithGivenSku(): void
    {
        $product = Product::create(
            tenantId: 1,
            categoryId: null,
            name: 'Widget',
            slug: 'widget',
            description: 'A widget.',
            sku: new SKU('WIDGET-1'),
            price: Money::fromAmount(1999, 'USD'),
        );

        $this->assertNull($product->id());
        $this->assertSame(1, $product->tenantId());
        $this->assertSame('Widget', $product->name());
        $this->assertSame('WIDGET-1', $product->sku()->value());
        $this->assertSame(ProductStatus::Draft, $product->status());
        $this->assertFalse($product->isActive());
    }

    public function test_update_changesFieldsButNotSkuOrTenant(): void
    {
        $product = Product::create(
            tenantId: 1,
            categoryId: null,
            name: 'Widget',
            slug: 'widget',
            description: null,
            sku: new SKU('WIDGET-1'),
            price: Money::fromAmount(1999, 'USD'),
        );

        $product->update(
            categoryId: 7,
            name: 'Widget Pro',
            description: 'Now with more widget.',
            price: Money::fromAmount(2999, 'USD'),
            status: ProductStatus::Active,
            attributes: ['color' => 'blue'],
        );

        $this->assertSame(1, $product->tenantId());
        $this->assertSame('WIDGET-1', $product->sku()->value());
        $this->assertSame(7, $product->categoryId());
        $this->assertSame('Widget Pro', $product->name());
        $this->assertSame(2999, $product->price()->amount());
        $this->assertTrue($product->isActive());
        $this->assertSame(['color' => 'blue'], $product->attributes());
    }
}
