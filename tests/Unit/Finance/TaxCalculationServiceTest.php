<?php

namespace Tests\Unit\Finance;

use App\Modules\Finance\Domain\Entities\TaxRate;
use App\Modules\Finance\Domain\Services\TaxCalculationService;
use App\Modules\Finance\Domain\ValueObjects\Money;
use App\Modules\Finance\Domain\ValueObjects\TaxRegion;
use PHPUnit\Framework\TestCase;

class TaxCalculationServiceTest extends TestCase
{
    public function test_calculateTax_withEightPointFivePercent_roundsToNearestCent(): void
    {
        $subtotal = Money::fromAmount(10000, 'USD'); // $100.00
        $taxRate = TaxRate::create(1, new TaxRegion('US-CA'), 850); // 8.50%

        $tax = (new TaxCalculationService())->calculateTax($subtotal, $taxRate);

        $this->assertSame(850, $tax->amount()); // $8.50
        $this->assertSame('USD', $tax->currency());
    }

    public function test_calculateTax_withZeroPercent_returnsZero(): void
    {
        $subtotal = Money::fromAmount(10000, 'USD');
        $taxRate = TaxRate::create(1, new TaxRegion('US-CA'), 0);

        $tax = (new TaxCalculationService())->calculateTax($subtotal, $taxRate);

        $this->assertSame(0, $tax->amount());
    }

    public function test_calculateTotal_addsSubtotalAndTax(): void
    {
        $subtotal = Money::fromAmount(10000, 'USD');
        $tax = Money::fromAmount(850, 'USD');

        $total = (new TaxCalculationService())->calculateTotal($subtotal, $tax);

        $this->assertSame(10850, $total->amount());
        $this->assertSame('USD', $total->currency());
    }
}
