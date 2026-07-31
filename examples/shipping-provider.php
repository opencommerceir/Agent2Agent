<?php

/**
 * A minimal AI Agent script demonstrating Phase 4 Stage 2's Shipping
 * Provider Connector — the same `opencommerce/sdk` path
 * examples/woocommerce-sync.php demonstrates, applied to the three new
 * capabilities:
 *   - shipping.provider.rates
 *   - shipping.provider.fulfill
 *   - shipping.tracking.sync
 *
 * Prerequisites:
 *   1. `php artisan serve` running this app (default: http://localhost:8000).
 *   2. An Agent token with `shipping.providers.read`/`.create`/`.sync` — see
 *      packages/opencommerce-sdk/README.md's "Quick Start" section.
 *   3. `SHIPPING_PROVIDER=mock` is already the default (config/shipping.php),
 *      so no `.env` change is required — every call below runs against
 *      MockShippingProviderAdapter, not a live carrier (no real
 *      USPS/FedEx/DHL implementation exists yet).
 *   4. An existing, already-created Shipment id (via Stage 1's
 *      `shipping.shipment.create` — see examples/sample-agent.php or
 *      `tests/Feature/Shipping/ShippingCapabilityTest.php` for how one
 *      gets created from a real Order) to run the fulfill/sync steps
 *      against. Omit it to see only the rate preview step.
 *
 * Usage:
 *   php examples/shipping-provider.php <token> [shipment-id] [base-url]
 */

require __DIR__.'/../vendor/autoload.php';

use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\Exceptions\MCPException;
use OpenCommerce\SDK\MCPClient;

$token = $argv[1] ?? null;
$shipmentId = isset($argv[2]) ? (int) $argv[2] : null;
$baseUrl = $argv[3] ?? 'http://localhost:8000/mcp/v1';

if (! $token) {
    fwrite(STDERR, "Usage: php examples/shipping-provider.php <token> [shipment-id] [base-url]\n");
    exit(1);
}

$config = new MCPConfig(baseUrl: $baseUrl, token: $token);
$client = new MCPClient($config);

echo "=== shipping.provider.rates ===\n";
try {
    $result = $client->execute('shipping.provider.rates', [
        'weight_grams' => 2500,
        'destination' => [
            'street' => '123 Main St',
            'city' => 'Springfield',
            'state' => 'IL',
            'postalCode' => '62704',
            'country' => 'US',
        ],
    ]);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "Rate lookup failed: [{$e->errorCode}] {$e->getMessage()}\n");
    exit(1);
}

if ($shipmentId === null) {
    echo "\nNo shipment-id given — skipping fulfill/sync (see this file's own docblock).\n";
    exit(0);
}

echo "\n=== shipping.provider.fulfill ===\n";
try {
    $result = $client->execute('shipping.provider.fulfill', ['shipment_id' => $shipmentId]);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "Fulfill failed: [{$e->errorCode}] {$e->getMessage()}\n");
    exit(1);
}

// shipping.tracking.sync looks a Shipment up by its own internal tracking
// number (Stage 1's TRK-XXXXXXXX reference) — deliberately not the
// provider's own tracking number just printed above (a real provider's
// tracking number has no meaning to our own findByTrackingNumber() lookup;
// see ShipmentRepositoryInterface::findByTrackingNumber()'s own docblock).
echo "\n=== shipping.shipment.get (to read our own internal tracking number) ===\n";
try {
    $result = $client->execute('shipping.shipment.get', ['shipment_id' => $shipmentId]);
    $ownTrackingNumber = $result->getData()['shipment']['trackingNumber'];
    echo "trackingNumber: {$ownTrackingNumber}\n";
} catch (MCPException $e) {
    fwrite(STDERR, "Lookup failed: [{$e->errorCode}] {$e->getMessage()}\n");
    exit(1);
}

echo "\n=== shipping.tracking.sync ===\n";
try {
    $result = $client->execute('shipping.tracking.sync', ['tracking_number' => $ownTrackingNumber]);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "Tracking sync failed: [{$e->errorCode}] {$e->getMessage()}\n");
}
