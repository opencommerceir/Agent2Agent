<?php

namespace Tests\Unit\Finance;

use App\Modules\Finance\Domain\Entities\TaxRate;
use App\Modules\Finance\Domain\ValueObjects\TaxRegion;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaxRateTest extends TestCase
{
    public function test_create_setsActiveByDefault(): void
    {
        $taxRate = TaxRate::create(1, new TaxRegion('US-CA'), 850);

        $this->assertNull($taxRate->id());
        $this->assertSame(850, $taxRate->ratePercentage());
        $this->assertTrue($taxRate->isActive());
    }

    public function test_create_withNegativeRate_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TaxRate::create(1, new TaxRegion('US-CA'), -1);
    }

    public function test_create_withRateOver100Percent_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TaxRate::create(1, new TaxRegion('US-CA'), 10001);
    }

    public function test_update_changesRateAndActiveState(): void
    {
        $taxRate = TaxRate::create(1, new TaxRegion('US-CA'), 850);

        $taxRate->update(900, false);

        $this->assertSame(900, $taxRate->ratePercentage());
        $this->assertFalse($taxRate->isActive());
    }

    public function test_update_withOutOfRangeRate_throwsInvalidArgumentExceptionAndDoesNotChangeState(): void
    {
        $taxRate = TaxRate::create(1, new TaxRegion('US-CA'), 850);

        try {
            $taxRate->update(-5, false);
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertSame(850, $taxRate->ratePercentage());
        $this->assertTrue($taxRate->isActive());
    }
}
