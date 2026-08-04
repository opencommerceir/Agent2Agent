<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\ValueObjects\BillingCycle;
use DateTimeImmutable;

/**
 * Pure, framework-free — the one place BillingCycle's own interval math
 * lives, the same "one Domain Service owns the formula" shape
 * PricingService/TaxCalculationService/ShippingRateCalculator already
 * establish. Uses DateTimeImmutable::modify() (calendar-aware month/year
 * arithmetic — e.g. Jan 31 + 1 month correctly rolls to Mar 3, not a fixed
 * 30-day approximation), never Carbon, keeping this class dependency-free
 * like every other Domain Service in this codebase.
 */
final class SubscriptionBillingCalculator
{
    public function nextPeriodEnd(DateTimeImmutable $from, BillingCycle $cycle): DateTimeImmutable
    {
        return match ($cycle) {
            BillingCycle::Monthly => $from->modify('+1 month'),
            BillingCycle::Quarterly => $from->modify('+3 months'),
            BillingCycle::Yearly => $from->modify('+1 year'),
        };
    }
}
