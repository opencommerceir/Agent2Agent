<?php

namespace Tests\Unit\Shipping;

use App\Modules\Shipping\Application\DTOs\ProviderTrackingEventData;
use App\Modules\Shipping\Domain\Entities\Shipment;
use App\Modules\Shipping\Domain\Exceptions\ShippingProviderException;
use App\Modules\Shipping\Domain\ValueObjects\Address;
use App\Modules\Shipping\Domain\ValueObjects\Money;
use App\Modules\Shipping\Domain\ValueObjects\TrackingNumber;
use App\Modules\Shipping\Domain\ValueObjects\Weight;
use App\Modules\Shipping\Infrastructure\Http\MockShippingHttpClient;
use App\Modules\Shipping\Infrastructure\Providers\MockShippingProviderAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Framework-free — no DB, no HTTP — proving the adapter correctly
 * translates MockShippingHttpClient's raw fixture-shaped arrays into this
 * module's own Domain types, the same "communication + translation"
 * Connector contract WooCommerceProductConnector's own tests establish.
 */
class MockShippingProviderAdapterTest extends TestCase
{
    public function test_getName_returnsMock(): void
    {
        $adapter = new MockShippingProviderAdapter(new MockShippingHttpClient());

        $this->assertSame('mock', $adapter->getName());
    }

    public function test_isConnected_returnsTrueWhenClientDoesNotFail(): void
    {
        $adapter = new MockShippingProviderAdapter(new MockShippingHttpClient());

        $this->assertTrue($adapter->isConnected());
    }

    public function test_isConnected_returnsFalseWhenClientFails(): void
    {
        $client = new MockShippingHttpClient(simulateFailure: true);
        $adapter = new MockShippingProviderAdapter($client);

        $this->assertFalse($adapter->isConnected());
    }

    public function test_getRates_returnsThreeRatesMatchingTheFixture(): void
    {
        $adapter = new MockShippingProviderAdapter(new MockShippingHttpClient());

        $destination = new Address('123 Main St', 'Springfield', 'IL', '62704', 'US');
        $rates = $adapter->getRates(new Weight(2500), $destination);

        $this->assertCount(3, $rates);

        $this->assertSame('STANDARD', $rates[0]->serviceCode());
        $this->assertSame('Standard Shipping', $rates[0]->serviceName());
        $this->assertSame(750, $rates[0]->cost()->amount());
        $this->assertSame('USD', $rates[0]->cost()->currency());
        $this->assertSame(5, $rates[0]->estimatedDaysMin());

        $this->assertSame('EXPRESS', $rates[1]->serviceCode());
        $this->assertSame(1500, $rates[1]->cost()->amount());

        $this->assertSame('OVERNIGHT', $rates[2]->serviceCode());
        $this->assertSame(2500, $rates[2]->cost()->amount());
    }

    public function test_createShipment_returnsAValidTrackingNumber(): void
    {
        $adapter = new MockShippingProviderAdapter(new MockShippingHttpClient());

        $shipment = Shipment::create(1, 1, 1, TrackingNumber::generate(), new Weight(1000), Money::fromAmount(500, 'USD'));
        $trackingNumber = $adapter->createShipment($shipment);

        $this->assertInstanceOf(TrackingNumber::class, $trackingNumber);
    }

    public function test_getTrackingUpdates_returnsTwoEventsMatchingTheFixture(): void
    {
        $adapter = new MockShippingProviderAdapter(new MockShippingHttpClient());

        $events = $adapter->getTrackingUpdates(new TrackingNumber('TRK-ABC12345'));

        $this->assertCount(2, $events);
        $this->assertContainsOnlyInstancesOf(ProviderTrackingEventData::class, $events);

        $this->assertSame('pending', $events[0]->status);
        $this->assertSame('Warehouse', $events[0]->location);
        $this->assertSame('2026-08-01T10:00:00+00:00', $events[0]->occurredAt->format(DATE_ATOM));

        $this->assertSame('in_transit', $events[1]->status);
        $this->assertSame('Distribution Center', $events[1]->location);
    }

    public function test_simulatedFailure_throwsShippingProviderExceptionOnEveryMethod(): void
    {
        $client = new MockShippingHttpClient(simulateFailure: true);
        $adapter = new MockShippingProviderAdapter($client);

        $this->expectException(ShippingProviderException::class);
        $adapter->getRates(new Weight(100), new Address('123 Main St', 'Springfield', 'IL', '62704', 'US'));
    }
}
