<?php

namespace Tests\Unit\Nexus\Catalog;

use App\Domains\Nexus\Catalog\Domain\Entities\Product;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\ListingVerificationStatus;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function test_add_withValidData_createsProduct(): void
    {
        $product = Product::add(1, 'محصول آزمایشی', 'Test Product', Money::fromAmount(50000, 'IRT'), 10);

        $this->assertNull($product->id());
        $this->assertSame(1, $product->businessId());
        $this->assertSame('محصول آزمایشی', $product->nameFa());
        $this->assertSame('Test Product', $product->nameEn());
        $this->assertSame(50000, $product->price()->amount());
        $this->assertSame(10, $product->stockQuantity());
        $this->assertNull($product->attributes());
        $this->assertSame(ListingVerificationStatus::Pending, $product->verificationStatus());
        $this->assertFalse($product->isVerified());
    }

    public function test_verify_setsStatusToVerified(): void
    {
        $product = Product::add(1, 'محصول آزمایشی', 'Test Product', Money::fromAmount(50000, 'IRT'));

        $product->verify();

        $this->assertTrue($product->isVerified());
    }

    public function test_reject_setsStatusToRejected(): void
    {
        $product = Product::add(1, 'محصول آزمایشی', 'Test Product', Money::fromAmount(50000, 'IRT'));

        $product->reject();

        $this->assertSame(ListingVerificationStatus::Rejected, $product->verificationStatus());
        $this->assertFalse($product->isVerified());
    }

    public function test_update_changesAllMutableFields(): void
    {
        $product = Product::add(1, 'محصول آزمایشی', 'Test Product', Money::fromAmount(50000, 'IRT'));

        $product->update('محصول جدید', 'New Product', Money::fromAmount(75000, 'IRT'), 5, ['color' => 'red']);

        $this->assertSame('محصول جدید', $product->nameFa());
        $this->assertSame('New Product', $product->nameEn());
        $this->assertSame(75000, $product->price()->amount());
        $this->assertSame(5, $product->stockQuantity());
        $this->assertSame(['color' => 'red'], $product->attributes());
    }
}
