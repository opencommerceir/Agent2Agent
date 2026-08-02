<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\ProductVariant;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\VariantSKU;
use PHPUnit\Framework\TestCase;

class ProductVariantTest extends TestCase
{
    public function test_create_setsEveryFieldFromTheFactory(): void
    {
        $variant = ProductVariant::create(
            tenantId: 1,
            productId: 10,
            sku: new VariantSKU('TSHIRT-RED-L'),
            price: Money::fromAmount(1999, 'USD'),
            attributes: ['Color' => 'Red', 'Size' => 'L'],
            imageUrl: 'https://example.com/red-l.png',
        );

        $this->assertNull($variant->id());
        $this->assertSame(1, $variant->tenantId());
        $this->assertSame(10, $variant->productId());
        $this->assertSame('TSHIRT-RED-L', $variant->sku()->value());
        $this->assertSame(1999, $variant->price()->amount());
        $this->assertSame(['Color' => 'Red', 'Size' => 'L'], $variant->attributes());
        $this->assertSame('https://example.com/red-l.png', $variant->imageUrl());
        $this->assertTrue($variant->isActive());
    }

    public function test_create_defaultsToActiveWithNoImage(): void
    {
        $variant = ProductVariant::create(
            tenantId: 1,
            productId: 10,
            sku: new VariantSKU('TSHIRT-RED-L'),
            price: Money::fromAmount(1999, 'USD'),
            attributes: ['Color' => 'Red'],
        );

        $this->assertTrue($variant->isActive());
        $this->assertNull($variant->imageUrl());
    }

    public function test_update_changesPriceImageAndActiveStatus(): void
    {
        $variant = ProductVariant::create(
            tenantId: 1,
            productId: 10,
            sku: new VariantSKU('TSHIRT-RED-L'),
            price: Money::fromAmount(1999, 'USD'),
            attributes: ['Color' => 'Red'],
        );

        $variant->update(Money::fromAmount(2499, 'USD'), 'https://example.com/new.png', false);

        $this->assertSame(2499, $variant->price()->amount());
        $this->assertSame('https://example.com/new.png', $variant->imageUrl());
        $this->assertFalse($variant->isActive());
    }

    public function test_update_neverChangesSkuOrAttributes(): void
    {
        $variant = ProductVariant::create(
            tenantId: 1,
            productId: 10,
            sku: new VariantSKU('TSHIRT-RED-L'),
            price: Money::fromAmount(1999, 'USD'),
            attributes: ['Color' => 'Red', 'Size' => 'L'],
        );

        $variant->update(Money::fromAmount(2499, 'USD'), null, true);

        $this->assertSame('TSHIRT-RED-L', $variant->sku()->value());
        $this->assertSame(['Color' => 'Red', 'Size' => 'L'], $variant->attributes());
    }

    public function test_update_bumpsUpdatedAt(): void
    {
        $variant = ProductVariant::create(
            tenantId: 1,
            productId: 10,
            sku: new VariantSKU('TSHIRT-RED-L'),
            price: Money::fromAmount(1999, 'USD'),
            attributes: ['Color' => 'Red'],
        );

        $originalUpdatedAt = $variant->updatedAt();
        usleep(1000);
        $variant->update(Money::fromAmount(2499, 'USD'), null, true);

        $this->assertGreaterThan($originalUpdatedAt, $variant->updatedAt());
    }
}
