<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\PaymentSession;

interface PaymentSessionRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?PaymentSession;

    /**
     * Tenant-**unscoped** lookup by id alone — exists only for the
     * public gateway callback/webhook routes (`routes/payments.php`),
     * which have no authenticated Agent/tenant identity at all (the
     * external gateway calling back knows nothing about this
     * platform's own tenancy). Safe despite the missing scope check:
     * `ConfirmRedirectPaymentAction` always re-verifies with the
     * gateway's own API before trusting anything — knowing a session id
     * alone can never fake a successful payment, at worst it wastes one
     * `verify()` call against a session that isn't yours. Never call
     * this from an MCP/Agent-facing path — `findById()` above is the
     * tenant-scoped one every authenticated caller must use instead.
     */
    public function findByIdUnscoped(int $id): ?PaymentSession;

    /**
     * Looks a session up by the gateway's own reference (Zibal's
     * trackId, Stripe's Checkout Session id) — the Stripe webhook path's
     * only way to find the right session, since a webhook payload
     * carries no tenant-scoped local id at all.
     */
    public function findByProviderReference(string $gateway, string $providerReference): ?PaymentSession;

    public function save(PaymentSession $session): PaymentSession;
}
