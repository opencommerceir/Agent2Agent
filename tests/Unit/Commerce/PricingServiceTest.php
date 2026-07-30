<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Services\PricingService;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\TaxRate;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the exact worked example this stage specified: subtotal 100,
 * 9% tax, 10% coupon discount -> tax 9, discount 10, total 99. Tax is
 * always computed on the subtotal, never on the discounted amount
 * (PricingService's own docblock).
 */
class PricingServiceTest extends TestCase
{
    public function test_calculate_withoutDiscount_appliesTaxOnSubtotal(): void
    {
        $breakdown = (new PricingService())->calculate(
            Money::fromAmount(10000, 'USD'),
            new TaxRate(9.0),
        );

        $this->assertSame(10000, $breakdown->subtotal->amount());
        $this->assertSame(900, $breakdown->tax->amount());
        $this->assertSame(0, $breakdown->discount->amount());
        $this->assertSame(10900, $breakdown->total->amount());
    }

    public function test_calculate_withDiscount_subtractsDiscountAfterAddingTax(): void
    {
        $breakdown = (new PricingService())->calculate(
            Money::fromAmount(10000, 'USD'),
            new TaxRate(9.0),
            Money::fromAmount(1000, 'USD'),
        );

        $this->assertSame(900, $breakdown->tax->amount());
        $this->assertSame(1000, $breakdown->discount->amount());
        $this->assertSame(9900, $breakdown->total->amount()); // 10000 + 900 - 1000
    }

    public function test_calculate_withZeroTaxRate_appliesNoTax(): void
    {
        $breakdown = (new PricingService())->calculate(
            Money::fromAmount(10000, 'USD'),
            new TaxRate(0),
        );

        $this->assertSame(0, $breakdown->tax->amount());
        $this->assertSame(10000, $breakdown->total->amount());
    }

    public function test_calculate_neverProducesANegativeTotal(): void
    {
        $breakdown = (new PricingService())->calculate(
            Money::fromAmount(100, 'USD'),
            new TaxRate(0),
            Money::fromAmount(500, 'USD'), // an oversized discount, clamped elsewhere in practice
        );

        $this->assertSame(0, $breakdown->total->amount());
    }
}
