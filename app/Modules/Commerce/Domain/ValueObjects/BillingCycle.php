<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * How often a SubscriptionPlan bills. The actual interval math (how many
 * months to add) lives on SubscriptionBillingCalculator, not here — this
 * enum stays plain data, the same "enum is data, a Domain Service owns the
 * formula" split PricingService/ShippingRateCalculator already establish
 * relative to their own input enums.
 */
enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
}
