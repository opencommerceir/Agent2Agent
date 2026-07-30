<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Infrastructure\Connectors\MockProductConnector;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Feature-level (not Unit) because it needs CommerceServiceProvider::boot()
 * to actually have run and registered 'mock' into the real container —
 * that wiring is exactly what this test verifies, not just the connector
 * class in isolation (Unit\Commerce\ConnectorTest covers that).
 */
class MockConnectorTest extends TestCase
{
    public function test_container_hasMockConnectorRegisteredByCommerceServiceProvider(): void
    {
        $registry = app(ConnectorRegistry::class);

        $this->assertContains('mock', $registry->registeredProductConnectors());
    }

    public function test_getProductConnector_withMockName_returnsMockProductConnectorInstance(): void
    {
        $connector = app(ConnectorRegistry::class)->getProductConnector('mock');

        $this->assertInstanceOf(MockProductConnector::class, $connector);
        $this->assertNotEmpty($connector->getProducts());
    }

    public function test_getProductConnector_withUnregisteredName_throwsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ConnectorRegistry::class)->getProductConnector('shopify');
    }
}
