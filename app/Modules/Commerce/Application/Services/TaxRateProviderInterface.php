<?php

namespace App\Modules\Commerce\Application\Services;

/**
 * Outbound port Commerce owns for "what tax rate applies to this
 * checkout" — Commerce defines the contract, Finance provides the real
 * implementation (CommerceTaxRateProvider), the exact same Dependency
 * Inversion direction PaymentGatewayInterface already established (this
 * module defines what it needs; whoever can answer it binds the
 * implementation). Commerce never imports anything from
 * `App\Modules\Finance\*` — not even to typehint this interface's own
 * implementation — keeping Commerce fully able to run standalone with
 * NullTaxRateProvider bound by default (CommerceServiceProvider::register()).
 *
 * Returns null, never throws, when no rate is configured — "I don't
 * know" is a normal, expected answer (no Finance module installed yet,
 * or a tenant that hasn't configured tax at all), not an error condition.
 * CalculatePricingAction/ProcessPaymentAction both fall back to their own
 * hardcoded default when this returns null, so a Commerce deployment
 * with no Finance module behaves exactly as it did before this
 * interface existed.
 */
interface TaxRateProviderInterface
{
    /**
     * @return float|null a percentage (0-100, e.g. 8.5), or null if no
     *                     rate is configured for this tenant/region
     */
    public function getRatePercent(int $tenantId, ?string $region): ?float;
}
