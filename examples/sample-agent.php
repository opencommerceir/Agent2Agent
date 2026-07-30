<?php

/**
 * A minimal, standalone AI Agent script — proof that a plain PHP script
 * outside this Laravel app can discover and execute OpenCommerce
 * capabilities using nothing but `composer require opencommerce/sdk`.
 *
 * Prerequisites:
 *   1. `php artisan serve` running this app (default: http://localhost:8000)
 *   2. An Agent token — generate one via GenerateAgentTokenAction, or see
 *      the "Quick Start" section of packages/opencommerce-sdk/README.md
 *      for a copy-pasteable Tinker snippet that creates a Tenant, Org,
 *      Agent, grants the three demo.* permissions, and prints a token.
 *
 * Usage:
 *   php examples/sample-agent.php <token> [base-url]
 */

require __DIR__.'/../vendor/autoload.php';

use OpenCommerce\SDK\Config\MCPConfig;
use OpenCommerce\SDK\Exceptions\MCPException;
use OpenCommerce\SDK\MCPClient;

$token = $argv[1] ?? null;
$baseUrl = $argv[2] ?? 'http://localhost:8000/mcp/v1';

if (! $token) {
    fwrite(STDERR, "Usage: php examples/sample-agent.php <token> [base-url]\n");
    exit(1);
}

$config = new MCPConfig(baseUrl: $baseUrl, token: $token);
$client = new MCPClient($config);

echo "=== Available Capabilities ===\n";
try {
    $capabilities = $client->discoverCapabilities();
    foreach ($capabilities as $capability) {
        echo "- {$capability->name}: {$capability->description}\n";
    }
} catch (MCPException $e) {
    fwrite(STDERR, "Discovery failed: [{$e->errorCode}] {$e->getMessage()}\n");
    exit(1);
}

echo "\n=== demo.tools.echo ===\n";
try {
    $result = $client->execute('demo.tools.echo', ['message' => 'Hello from AI Agent!']);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "demo.tools.echo failed: [{$e->errorCode}] {$e->getMessage()}\n");
}

echo "\n=== demo.tools.time ===\n";
try {
    $result = $client->execute('demo.tools.time');
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "demo.tools.time failed: [{$e->errorCode}] {$e->getMessage()}\n");
}

echo "\n=== demo.tools.calculator ===\n";
try {
    $result = $client->execute('demo.tools.calculator', [
        'operation' => 'multiply',
        'a' => 42,
        'b' => 10,
    ]);
    print_r($result->getData());
} catch (MCPException $e) {
    fwrite(STDERR, "demo.tools.calculator failed: [{$e->errorCode}] {$e->getMessage()}\n");
}

echo "\n=== Negative test: unknown capability ===\n";
try {
    // Well-formed (domain.resource.action) but genuinely unregistered —
    // a malformed name like "demo.nonexistent" would fail format
    // validation (VALIDATION_ERROR) before ever reaching the "does this
    // exist" check this test is meant to demonstrate (NOT_FOUND).
    $client->execute('demo.tools.nonexistent', []);
} catch (MCPException $e) {
    echo "Correctly rejected: [{$e->errorCode}] {$e->getMessage()}\n";
}
