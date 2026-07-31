<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Services\WooCommerceProductMapper;
use App\Modules\Commerce\Domain\ValueObjects\WooCommerceProductData;
use PHPUnit\Framework\TestCase;

class WooCommerceProductMapperTest extends TestCase
{
    public function test_toUCP_withFullPayload_mapsEveryFieldToUCPProduct(): void
    {
        $data = WooCommerceProductData::fromArray([
            'id' => 123,
            'name' => 'WooCommerce T-Shirt',
            'slug' => 'woo-tshirt',
            'type' => 'simple',
            'status' => 'publish',
            'price' => '29.99',
            'regular_price' => '29.99',
            'description' => 'A beautiful t-shirt from WooCommerce',
            'short_description' => 'Premium cotton t-shirt',
            'sku' => 'WOO-TSHIRT-001',
            'stock_quantity' => 50,
            'manage_stock' => true,
            'categories' => [['id' => 9, 'name' => 'Clothing', 'slug' => 'clothing']],
            'images' => [['id' => 100, 'src' => 'https://example.com/tshirt.jpg']],
        ]);

        $product = (new WooCommerceProductMapper())->toUCP($data);

        $this->assertSame('123', $product->externalId);
        $this->assertSame('woocommerce', $product->sourceSystem);
        $this->assertSame('WOO-TSHIRT-001', $product->sku);
        $this->assertSame('WooCommerce T-Shirt', $product->name);
        $this->assertSame('A beautiful t-shirt from WooCommerce', $product->description);
        $this->assertSame(2999, $product->priceAmount); // "29.99" -> 2999 cents, never a float
        $this->assertSame('USD', $product->priceCurrency);
        $this->assertSame(['9'], $product->categoryIds);
        $this->assertTrue($product->isAvailable);
        $this->assertSame('Clothing', $product->attributes['category_name']);
        $this->assertSame('https://example.com/tshirt.jpg', $product->attributes['image_url']);
        $this->assertSame(50, $product->attributes['stock_quantity']);
        $this->assertSame('woocommerce', $product->attributes['source_system']);
        $this->assertSame('123', (string) $product->attributes['external_id']);
    }

    public function test_toUCP_withDraftStatus_isNotAvailable(): void
    {
        $data = WooCommerceProductData::fromArray([
            'id' => 5,
            'name' => 'Hidden Product',
            'status' => 'draft',
            'price' => '10.00',
            'sku' => 'HIDDEN-1',
        ]);

        $product = (new WooCommerceProductMapper())->toUCP($data);

        $this->assertFalse($product->isAvailable);
    }

    public function test_toUCP_withMissingCategoriesAndImages_usesNullAttributes(): void
    {
        $data = WooCommerceProductData::fromArray([
            'id' => 6,
            'name' => 'Bare Product',
            'status' => 'publish',
            'price' => '5.00',
            'sku' => 'BARE-1',
        ]);

        $product = (new WooCommerceProductMapper())->toUCP($data);

        $this->assertSame([], $product->categoryIds);
        $this->assertNull($product->attributes['category_name']);
        $this->assertNull($product->attributes['image_url']);
    }

    public function test_toUCP_withEmptySku_fallsBackToGeneratedSku(): void
    {
        $data = WooCommerceProductData::fromArray([
            'id' => 999,
            'name' => 'No SKU Product',
            'status' => 'publish',
            'price' => '1.00',
        ]);

        $product = (new WooCommerceProductMapper())->toUCP($data);

        $this->assertSame('WOO-999', $product->sku);
    }

    public function test_toUCP_withEmptyPrice_fallsBackToRegularPrice(): void
    {
        $data = WooCommerceProductData::fromArray([
            'id' => 7,
            'name' => 'Sale Ended',
            'status' => 'publish',
            'price' => '',
            'regular_price' => '19.50',
            'sku' => 'SALE-ENDED',
        ]);

        $product = (new WooCommerceProductMapper())->toUCP($data);

        $this->assertSame(1950, $product->priceAmount);
    }

    public function test_toUCP_withCustomCurrency_usesGivenCurrency(): void
    {
        $data = WooCommerceProductData::fromArray([
            'id' => 8,
            'name' => 'Euro Product',
            'status' => 'publish',
            'price' => '9.99',
            'sku' => 'EURO-1',
        ]);

        $product = (new WooCommerceProductMapper())->toUCP($data, 'EUR');

        $this->assertSame('EUR', $product->priceCurrency);
    }
}
