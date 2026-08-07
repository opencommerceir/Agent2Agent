<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\Exceptions\PaymentGatewayNotFoundException;

/**
 * In-memory lookup of "which redirect-based gateway handles which name"
 * (e.g. 'mock', 'zibal', 'stripe') — mirrors `ConnectorRegistry`/
 * `ShippingProviderRegistry`/`ChannelSenderRegistry` exactly (HANDOFF §3
 * pattern #15, the fourth application of this shape). Registered once in
 * `CommerceServiceProvider::boot()`. Adding a new gateway means
 * implementing `RedirectPaymentGatewayInterface` and calling `register()`
 * here under a new name — no other file in this class needs to change
 * (see `docs/payment-gateways.md`).
 */
final class PaymentGatewayRegistry
{
    /**
     * @var array<string, RedirectPaymentGatewayInterface>
     */
    private array $gateways = [];

    public function register(string $name, RedirectPaymentGatewayInterface $gateway): void
    {
        $this->gateways[$name] = $gateway;
    }

    public function get(string $name): RedirectPaymentGatewayInterface
    {
        if (! isset($this->gateways[$name])) {
            throw new PaymentGatewayNotFoundException("No payment gateway registered under [{$name}].");
        }

        return $this->gateways[$name];
    }

    /**
     * @return list<string>
     */
    public function registered(): array
    {
        return array_keys($this->gateways);
    }
}
