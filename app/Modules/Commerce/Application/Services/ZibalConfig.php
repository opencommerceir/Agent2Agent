<?php

namespace App\Modules\Commerce\Application\Services;

/**
 * Zibal gateway settings, read from config/payment_gateways.php (itself
 * backed by ZIBAL_* env vars) — never read directly from env() outside
 * this one factory, the same boundary WooCommerceConfig/
 * ShippingProviderConfig already establish. `merchant` defaults to
 * Zibal's own public test account (`zibal`, per their docs' own
 * "حساب تستی" section) rather than an empty string — a fresh
 * `composer install` can smoke-test the real sandbox with zero setup,
 * the same role `OPENROUTER_MODEL`'s own free-model default plays
 * (§7.32).
 */
final class ZibalConfig
{
    public function __construct(
        public readonly string $merchant,
        public readonly string $baseUrl,
        public readonly int $timeoutSeconds,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            merchant: (string) config('payment_gateways.zibal.merchant', 'zibal'),
            baseUrl: rtrim((string) config('payment_gateways.zibal.base_url', 'https://gateway.zibal.ir'), '/'),
            timeoutSeconds: (int) config('payment_gateways.zibal.timeout', 15),
        );
    }
}
