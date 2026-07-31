<?php

namespace App\Modules\Shipping\Application\Services;

/**
 * Shipping provider settings, read from config/shipping.php (itself
 * backed by SHIPPING_PROVIDER env vars) — never read directly from env()
 * outside this one factory, the same "config/commerce.php +
 * WooCommerceConfig::fromConfig()" boundary Commerce already establishes.
 */
final class ShippingProviderConfig
{
    public function __construct(
        public readonly string $defaultProvider,
        public readonly string $endpoint,
        public readonly string $apiKey,
        public readonly int $timeoutSeconds = 10,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            defaultProvider: (string) config('shipping.provider', 'mock'),
            endpoint: rtrim((string) config('shipping.mock.endpoint', ''), '/'),
            apiKey: (string) config('shipping.mock.api_key', ''),
            timeoutSeconds: (int) config('shipping.mock.timeout', 10),
        );
    }
}
