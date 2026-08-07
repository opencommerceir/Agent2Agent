<?php

// Published to config/opencommerce.php in a consuming Laravel application
// (`php artisan vendor:publish --tag=opencommerce-config`). Two ways to
// point at a deployment, mirroring the two constructors the underlying
// opencommerce/sdk MCPConfig already offers (packages/opencommerce-sdk/src/Config/MCPConfig.php):
//
// 1. Set OPENCOMMERCE_BASE_URL directly (e.g. https://api.opencommerce.ir/mcp/v1)
//    when you already know the exact, version-suffixed URL.
// 2. Or set OPENCOMMERCE_HOST + OPENCOMMERCE_VERSION and let the
//    ServiceProvider build it for you via MCPConfig::forVersion() — the
//    same one-argument-change v1 -> v2 migration path the PHP SDK's own
//    docblock describes.
//
// base_url always wins if both are set.
return [

    'base_url' => env('OPENCOMMERCE_BASE_URL'),

    'host' => env('OPENCOMMERCE_HOST', 'https://api.opencommerce.ir'),

    'version' => env('OPENCOMMERCE_VERSION', 'v1'),

    'token' => env('OPENCOMMERCE_TOKEN'),

    'timeout' => env('OPENCOMMERCE_TIMEOUT', 30),

    'verify_ssl' => env('OPENCOMMERCE_VERIFY_SSL', true),

];
