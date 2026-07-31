<?php

return [

    /*
    |--------------------------------------------------------------------
    | WooCommerce Connector
    |--------------------------------------------------------------------
    |
    | Credentials for the real WooCommerceClient (Guzzle-backed) used by
    | WooCommerceProductConnector. Consumer Key/Secret are WooCommerce's
    | own REST API auth scheme (Settings > Advanced > REST API on the
    | store). Left empty by default — no store is configured out of the
    | box; tests use MockWooCommerceHttpClient instead of these values.
    |
    */

    'woocommerce' => [
        'store_url' => env('WOOCOMMERCE_STORE_URL', ''),
        'consumer_key' => env('WOOCOMMERCE_CONSUMER_KEY', ''),
        'consumer_secret' => env('WOOCOMMERCE_CONSUMER_SECRET', ''),
        'currency' => env('WOOCOMMERCE_CURRENCY', 'USD'),
        'timeout' => env('WOOCOMMERCE_TIMEOUT', 10),
    ],

];
