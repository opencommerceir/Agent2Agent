<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\ValueObjects\TaxRate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaxRateTest extends TestCase
{
    public function test_construct_withValidPercentage_setsValue(): void
    {
        $rate = new TaxRate(9.0);

        $this->assertSame(9.0, $rate->value());
    }

    public function test_construct_withNegativeValue_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TaxRate(-1);
    }

    public function test_construct_withValueAboveHundred_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TaxRate(101);
    }

    public function test_construct_withBoundaryValues_isAccepted(): void
    {
        $this->assertSame(0.0, (new TaxRate(0))->value());
        $this->assertSame(100.0, (new TaxRate(100))->value());
    }
}
