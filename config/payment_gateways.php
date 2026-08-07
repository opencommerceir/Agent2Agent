<?php

/**
 * Real Payment Gateways — Zibal + Stripe (§7.37).
 *
 * `default` is which gateway `InitiatePaymentAction` resolves when a
 * caller doesn't name one explicitly. Left as `mock` — no real charge
 * attempt is ever the default for a fresh `composer install`/test run,
 * the same "safe default, explicit opt-in for real infra" reasoning
 * `PLANNER_TYPE=deterministic`/`CACHE_STORE=database` already establish
 * elsewhere in this codebase.
 */
return [

    'default' => env('PAYMENT_GATEWAY', 'mock'),

    'zibal' => [
        // Zibal's own designated public test account — real, safe to
        // call, no live merchant contract needed (see their own docs'
        // "حساب تستی" section). A production deployment overrides this
        // with a real merchant id issued from Zibal's own panel.
        'merchant' => env('ZIBAL_MERCHANT', 'zibal'),
        'base_url' => env('ZIBAL_BASE_URL', 'https://gateway.zibal.ir'),
        'timeout' => env('ZIBAL_TIMEOUT', 15),
    ],

    'stripe' => [
        // Empty by default — same "constructs fine, fails loud only
        // when actually called" shape every credential-bearing config
        // block in this codebase already establishes (no live Stripe
        // credentials exist in this dev environment, §7.37).
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
        'timeout' => env('STRIPE_TIMEOUT', 15),
    ],

];
