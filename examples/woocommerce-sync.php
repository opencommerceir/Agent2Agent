<?php

/**
 * A minimal AI Agent script that syncs a WooCommerce store's catalog into
 * OpenCommerce, then looks up one product live from the store — the
 * same `opencommerce/sdk` path examples/sample-agent.php demonstrates,
 * applied to the two Stage 6 capabilities:
 *   - commerce.woocommerce.sync
 *   - commerce.woocommerce.get
 *
 * Prerequisites:
 *   1. `php artisan serve` running this app (default: http://localhost:8000)
 *   2. An Agent token with the `commerce.connectors.sync` and
 *      `commerce.connectors.read` permissions — see
 *      packages/opencommerce-sdk/README.md's "Quick Start" section.
 *   3. WOOCOMMERCE_STORE_URL/WOOCOMMERCE_CONSUMER_KEY/WOOCOMMERCE_CONSUMER_SECRET
 *      set in .env if you want to sync a real store. Without them, the
 *      'woocommerce' Connector still exists but every call will fail
 *      against an empty base URL — expected until a real store is
 *      configured (same "needs live credentials to test honestly"
 *      reasoning HANDOFF gives for every Connector).
 *
 * Usage:
 *   php examples/woocommerce-sync.php <token> [base-url]
 */

require __DIR__.'/../vendor/autoload.php';

use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\Exceptions\MCPException;
use OpenCommerce\SDK\MCPClient;

$token = $argv[1] ?? null;
$baseUrl = $argv[2] ?? 'http://localhost:8000/mcp/v1';

if (! $token) {
    fwrite(STDERR, "Usage: php examples/woocommerce-sync.php <token> [base-url]\n");
    exit(1);
}

$config = new MCPConfig(baseUrl: $baseUrl, token: $token);
$client = new MCPClient($config);

echo "=== commerce.woocommerce.sync ===\n";
try {
    $result = $client->execute('commerce.woocommerce.sync', ['limit' => 20]);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "Sync failed: [{$e->errorCode}] {$e->getMessage()}\n");
    exit(1);
}

echo "\n=== commerce.product.search (proving the synced products are searchable) ===\n";
try {
    $result = $client->execute('commerce.product.search', ['query' => '', 'limit' => 10]);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "Search failed: [{$e->errorCode}] {$e->getMessage()}\n");
}

echo "\n=== commerce.woocommerce.get (live lookup, not the local catalog) ===\n";
try {
    $result = $client->execute('commerce.woocommerce.get', ['external_id' => '123']);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "Lookup failed: [{$e->errorCode}] {$e->getMessage()}\n");
}
