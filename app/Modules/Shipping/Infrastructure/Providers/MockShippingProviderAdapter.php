<?php

namespace App\Modules\Shipping\Infrastructure\Providers;

use App\Modules\Shipping\Application\DTOs\ProviderTrackingEventData;
use App\Modules\Shipping\Application\Services\ShippingHttpClientInterface;
use App\Modules\Shipping\Domain\Entities\Shipment;
use App\Modules\Shipping\Domain\Exceptions\ShippingProviderException;
use App\Modules\Shipping\Domain\Services\ShippingProviderInterface;
use App\Modules\Shipping\Domain\ValueObjects\Address;
use App\Modules\Shipping\Domain\ValueObjects\Money;
use App\Modules\Shipping\Domain\ValueObjects\ShippingRate;
use App\Modules\Shipping\Domain\ValueObjects\TrackingNumber;
use App\Modules\Shipping\Domain\ValueObjects\Weight;

/**
 * The one real `ShippingProviderInterface` implementation for `mock`
 * (correction #1 from this stage's plan — a single adapter, not a second
 * wrapping "Application Service"): fetches raw payloads via
 * `ShippingHttpClientInterface` and translates them into this module's
 * own Domain types — pure communication + translation, no business rules
 * (Connector Conventions), the exact shape `WooCommerceProductConnector`
 * already establishes.
 */
final class MockShippingProviderAdapter implements ShippingProviderInterface
{
    public function __construct(
        private readonly ShippingHttpClientInterface $client,
    ) {
    }

    public function getName(): string
    {
        return 'mock';
    }

    public function isConnected(): bool
    {
        try {
            $this->client->getRates([]);

            return true;
        } catch (ShippingProviderException) {
            return false;
        }
    }

    public function getRates(Weight $weight, Address $destination): array
    {
        $raw = $this->client->getRates([
            'weight_grams' => $weight->grams(),
            'destination' => $destination->toArray(),
        ]);

        return array_map(
            fn (array $rate) => new ShippingRate(
                Money::fromAmount((int) round($rate['total_price'] * 100), $rate['currency']),
                $rate['delivery_days'],
                $rate['delivery_days'],
                $rate['service_name'],
                $rate['service_code'],
            ),
            $raw['rates'],
        );
    }

    public function createShipment(Shipment $shipment): TrackingNumber
    {
        $raw = $this->client->createShipment([
            'order_id' => $shipment->orderId(),
            'weight_grams' => $shipment->weight()->grams(),
        ]);

        return new TrackingNumber($raw['tracking_number']);
    }

    public function getTrackingUpdates(TrackingNumber $trackingNumber): array
    {
        $raw = $this->client->getTrackingUpdates($trackingNumber->value());

        return array_map(
            fn (array $event) => ProviderTrackingEventData::fromArray($event),
            $raw['events'],
        );
    }
}
