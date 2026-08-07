<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\ValueObjects\Money;

/**
 * The contract a real, redirect-based payment gateway (Zibal, Stripe,
 * ...) implements — deliberately separate from `PaymentGatewayInterface`
 * (which stays exactly as it was: a synchronous "charge with card
 * details already in hand" contract `MockPaymentGateway` alone
 * satisfies). Real gateways don't work that way: both Zibal (request ->
 * redirect to a hosted page -> callback -> verify) and Stripe (Checkout
 * Session -> redirect -> webhook -> retrieve) are async, redirect-based,
 * "the buyer pays on the gateway's own page, never ours" flows — see
 * HANDOFF §7.37 for the full architecture-fork reasoning.
 *
 * `PaymentGatewayRegistry` is the seam that picks which implementation
 * runs by name (e.g. 'zibal', 'stripe') — the same Connector Pattern
 * `ConnectorRegistry`/`ShippingProviderRegistry` already establish
 * (HANDOFF §3 pattern #15). Adding a new gateway means implementing this
 * Interface and registering it under a name in
 * `CommerceServiceProvider::boot()` — nothing else needs to change (see
 * `docs/payment-gateways.md`).
 */
interface RedirectPaymentGatewayInterface
{
    /**
     * Identifies the gateway for `PaymentGatewayRegistry` lookups (e.g.
     * 'zibal', 'stripe').
     */
    public function getName(): string;

    /**
     * Starts a redirect-based charge. `$callbackUrl` is where the
     * gateway sends the buyer (and/or a server-to-server webhook) once
     * payment finishes; `$metadata` carries whatever context a specific
     * gateway can use (a `reference` string every implementation should
     * echo back if it can, an optional human-readable `description`,
     * an optional `mobile` number) — a gateway with no use for a given
     * key simply ignores it, the same "handler ignores fields it
     * doesn't need" convention `AuthContext`/Demo's own handlers already
     * establish.
     *
     * @param array<string, mixed> $metadata
     */
    public function initiate(Money $amount, string $callbackUrl, array $metadata): PaymentInitiationResult;

    /**
     * Confirms a charge server-to-server, by the gateway's own
     * `providerReference` (never by anything a caller/callback merely
     * *claims* — both Zibal's and Stripe's own docs are explicit that a
     * callback/webhook payload alone is never sufficient proof).
     */
    public function verify(string $providerReference): PaymentGatewayResult;

    /**
     * A read-only status check — never mutates anything on the
     * gateway's own side, unlike verify() (which, for some gateways,
     * is also the act of finalizing/closing out the session).
     */
    public function inquiry(string $providerReference): PaymentGatewayResult;
}
