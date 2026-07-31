<?php

namespace App\Modules\Shipping\Infrastructure\Http;

use App\Modules\Shipping\Application\Services\ShippingHttpClientInterface;
use App\Modules\Shipping\Domain\Exceptions\ShippingProviderException;
use App\Modules\Shipping\Domain\ValueObjects\TrackingNumber;

/**
 * Stands in for a live shipping provider's API until real credentials
 * exist to test against honestly (same reasoning
 * `MockWooCommerceHttpClient`/`MockProductConnector` already give). Returns
 * the exact JSON shape `tests/Fixtures/shipping-rates-response.json` /
 * `tests/Fixtures/tracking-updates-response.json` document, so swapping
 * this for a real HTTP-backed `ShippingHttpClientInterface` implementation
 * later requires no change to `MockShippingProviderAdapter` or anything
 * above it.
 *
 * Failure is opt-in via `simulateFailure()`, the same "deliberate,
 * documented test-triggering convention" `MockWooCommerceHttpClient`'s
 * own flag established, so `ShippingProviderException`'s path through
 * every Provider Action is actually exercisable in tests without real
 * network mocking.
 */
final class MockShippingHttpClient implements ShippingHttpClientInterface
{
    public function __construct(
        private bool $simulateFailure = false,
    ) {
    }

    public function simulateFailure(bool $shouldFail = true): void
    {
        $this->simulateFailure = $shouldFail;
    }

    public function getRates(array $payload): array
    {
        $this->guardAgainstSimulatedFailure();

        return [
            'rates' => [
                ['service_name' => 'Standard Shipping', 'service_code' => 'STANDARD', 'total_price' => 7.50, 'currency' => 'USD', 'delivery_days' => 5],
                ['service_name' => 'Express Shipping', 'service_code' => 'EXPRESS', 'total_price' => 15.00, 'currency' => 'USD', 'delivery_days' => 2],
                ['service_name' => 'Overnight Shipping', 'service_code' => 'OVERNIGHT', 'total_price' => 25.00, 'currency' => 'USD', 'delivery_days' => 1],
            ],
        ];
    }

    public function createShipment(array $payload): array
    {
        $this->guardAgainstSimulatedFailure();

        return ['tracking_number' => TrackingNumber::generate()->value()];
    }

    public function getTrackingUpdates(string $trackingNumber): array
    {
        $this->guardAgainstSimulatedFailure();

        return [
            'tracking_number' => $trackingNumber,
            'status' => 'in_transit',
            'events' => [
                ['status' => 'pending', 'location' => 'Warehouse', 'description' => 'Shipment created', 'timestamp' => '2026-08-01T10:00:00Z'],
                ['status' => 'in_transit', 'location' => 'Distribution Center', 'description' => 'Package in transit', 'timestamp' => '2026-08-01T14:00:00Z'],
            ],
        ];
    }

    private function guardAgainstSimulatedFailure(): void
    {
        if ($this->simulateFailure) {
            throw new ShippingProviderException('Simulated shipping provider API failure.');
        }
    }
}
