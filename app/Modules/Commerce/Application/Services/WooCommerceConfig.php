<?php

namespace App\Modules\Commerce\Application\Services;

/**
 * WooCommerce store credentials/settings, read from config/commerce.php
 * (itself backed by WOOCOMMERCE_* env vars) — never read directly from
 * env() outside this one factory, so every other class depending on
 * WooCommerce configuration depends on this plain DTO instead.
 */
final class WooCommerceConfig
{
    public function __construct(
        public readonly string $storeUrl,
        public readonly string $consumerKey,
        public readonly string $consumerSecret,
        public readonly string $currency = 'USD',
        public readonly int $timeoutSeconds = 10,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            storeUrl: rtrim((string) config('commerce.woocommerce.store_url', ''), '/'),
            consumerKey: (string) config('commerce.woocommerce.consumer_key', ''),
            consumerSecret: (string) config('commerce.woocommerce.consumer_secret', ''),
            currency: (string) config('commerce.woocommerce.currency', 'USD'),
            timeoutSeconds: (int) config('commerce.woocommerce.timeout', 10),
        );
    }
}
