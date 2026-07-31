<?php

return [

    /*
    |--------------------------------------------------------------------
    | Shipping Provider Connector
    |--------------------------------------------------------------------
    |
    | Which provider ShippingProviderRegistry resolves by default when an
    | MCP capability's `provider` input is omitted, plus the Mock
    | provider's own settings. Mirrors config/commerce.php's WooCommerce
    | block exactly. Left as 'mock' by default — no real carrier (USPS/
    | FedEx/DHL) has an implementation yet (see ShippingProviderName's own
    | docblock).
    |
    */

    'provider' => env('SHIPPING_PROVIDER', 'mock'),

    'mock' => [
        'endpoint' => env('SHIPPING_PROVIDER_ENDPOINT', 'https://api.mockshipping.com'),
        'api_key' => env('SHIPPING_PROVIDER_API_KEY', ''),
        'timeout' => env('SHIPPING_PROVIDER_TIMEOUT', 10),
    ],

];
