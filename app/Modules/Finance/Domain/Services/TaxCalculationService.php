<?php

namespace App\Modules\Finance\Domain\Services;

use App\Modules\Finance\Domain\Entities\TaxRate;
use App\Modules\Finance\Domain\ValueObjects\Money;

/**
 * The single formula owner for "how much tax does a TaxRate add to a
 * subtotal" — pure and framework-free, same shape Commerce's own
 * PricingService/CouponValidationService already establish: no
 * Repository dependency, no knowledge of *which* TaxRate applies (that
 * lookup belongs to the Action/adapter calling this), only how to
 * combine numbers it's given. ratePercentage is percentage*100
 * (TaxRate's own docblock), so dividing by 10,000 (not 100) converts it
 * to a fraction of the subtotal.
 */
final class TaxCalculationService
{
    public function calculateTax(Money $subtotal, TaxRate $taxRate): Money
    {
        $taxAmount = (int) round($subtotal->amount() * $taxRate->ratePercentage() / 10000);

        return Money::fromAmount($taxAmount, $subtotal->currency());
    }

    public function calculateTotal(Money $subtotal, Money $tax): Money
    {
        return Money::fromAmount($subtotal->amount() + $tax->amount(), $subtotal->currency());
    }
}
