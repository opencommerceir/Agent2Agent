<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PricingBreakdown;
use App\Modules\Commerce\Domain\ValueObjects\TaxRate;

/**
 * The single formula owner for "how do subtotal, tax, and discount
 * combine into a total" (Total = Subtotal + Tax - Discount) — both
 * CalculatePricingAction's pure preview and ProcessPaymentAction's real
 * checkout go through this, so the numbers a preview shows an Agent are
 * guaranteed identical to what actually gets charged.
 *
 * Tax is always computed on the subtotal, never on the discounted
 * amount — the two adjustments are independent, not compounding (this
 * stage's explicit rule, matches the requested example: subtotal 100 +
 * tax 9 - discount 10 = 99, not tax computed on 90).
 *
 * No dependency on a repository or a specific tenant's configured tax
 * rate (none exists yet — see CalculatePricingAction's docblock): this
 * service only knows how to combine numbers it's given.
 */
final class PricingService
{
    public function calculate(Money $subtotal, TaxRate $taxRate, ?Money $discount = null): PricingBreakdown
    {
        $discount ??= Money::fromAmount(0, $subtotal->currency());

        $taxAmount = (int) round($subtotal->amount() * $taxRate->value() / 100);
        $totalAmount = max(0, $subtotal->amount() + $taxAmount - $discount->amount());

        return new PricingBreakdown(
            subtotal: $subtotal,
            tax: Money::fromAmount($taxAmount, $subtotal->currency()),
            discount: Money::fromAmount($discount->amount(), $subtotal->currency()),
            total: Money::fromAmount($totalAmount, $subtotal->currency()),
        );
    }
}
