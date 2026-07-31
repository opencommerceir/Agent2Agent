<?php

namespace App\Modules\Shipping\Application\Services;

/**
 * Outbound port to an external shipping provider's REST API — mirrors
 * Commerce's own `WooCommerceClientInterface` exactly. Returns raw
 * decoded JSON — never a Domain type — so the translation boundary stays
 * entirely inside the provider adapter (`MockShippingProviderAdapter`),
 * the same communication/translation split every Connector follows.
 */
interface ShippingHttpClientInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function getRates(array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createShipment(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function getTrackingUpdates(string $trackingNumber): array;
}
