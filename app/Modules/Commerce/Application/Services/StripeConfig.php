<?php

namespace App\Modules\Commerce\Application\Services;

/**
 * Stripe gateway settings, read from config/payment_gateways.php (itself
 * backed by STRIPE_* env vars) — same "empty string, still constructs,
 * fails loud only when actually called" shape every credential-bearing
 * Config class in this codebase already establishes (no live Stripe
 * credentials exist in this dev environment to test honestly against,
 * §7.37).
 */
final class StripeConfig
{
    public function __construct(
        public readonly string $secretKey,
        public readonly string $webhookSecret,
        public readonly string $baseUrl,
        public readonly int $timeoutSeconds,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            secretKey: (string) config('payment_gateways.stripe.secret_key', ''),
            webhookSecret: (string) config('payment_gateways.stripe.webhook_secret', ''),
            baseUrl: rtrim((string) config('payment_gateways.stripe.base_url', 'https://api.stripe.com'), '/'),
            timeoutSeconds: (int) config('payment_gateways.stripe.timeout', 15),
        );
    }
}
