<?php

namespace App\Modules\Commerce\Application\Services;

/**
 * Commerce's own default TaxRateProviderInterface implementation — always
 * answers "I don't know" (same "deferred, needs the real thing to test
 * against honestly" shape MockPaymentGateway/MockProductConnector had
 * before this module's real integrations existed). Bound by
 * CommerceServiceProvider::register() so Commerce works standalone with
 * its old hardcoded-default-tax-rate behavior; FinanceServiceProvider
 * overrides this binding with CommerceTaxRateProvider when the Finance
 * module is loaded (see that class's docblock).
 */
final class NullTaxRateProvider implements TaxRateProviderInterface
{
    public function getRatePercent(int $tenantId, ?string $region): ?float
    {
        return null;
    }
}
