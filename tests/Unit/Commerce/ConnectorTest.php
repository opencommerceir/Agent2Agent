<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\UCP\UCPProduct;
use App\Modules\Commerce\Infrastructure\Connectors\MockProductConnector;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    public function test_getName_returnsMock(): void
    {
        $this->assertSame('mock', (new MockProductConnector())->getName());
    }

    public function test_isConnected_returnsTrue(): void
    {
        $this->assertTrue((new MockProductConnector())->isConnected());
    }

    public function test_getProducts_returnsListOfUcpProductInstances(): void
    {
        $products = (new MockProductConnector())->getProducts();

        $this->assertNotEmpty($products);
        $this->assertContainsOnlyInstancesOf(UCPProduct::class, $products);
    }

    public function test_getProduct_withKnownExternalId_returnsMatchingProduct(): void
    {
        $product = (new MockProductConnector())->getProduct('mock-1');

        $this->assertNotNull($product);
        $this->assertSame('mock-1', $product->externalId);
    }

    public function test_getProduct_withUnknownExternalId_returnsNull(): void
    {
        $this->assertNull((new MockProductConnector())->getProduct('does-not-exist'));
    }
}
