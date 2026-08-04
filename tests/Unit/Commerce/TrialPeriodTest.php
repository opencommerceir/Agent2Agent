<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\ValueObjects\TrialPeriod;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TrialPeriodTest extends TestCase
{
    public function test_hasTrial_withPositiveDays_isTrue(): void
    {
        $this->assertTrue((new TrialPeriod(7))->hasTrial());
    }

    public function test_hasTrial_withZeroDays_isFalse(): void
    {
        $this->assertFalse((new TrialPeriod(0))->hasTrial());
    }

    public function test_negativeDays_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TrialPeriod(-1);
    }

    public function test_days_returnsConstructedValue(): void
    {
        $this->assertSame(14, (new TrialPeriod(14))->days());
    }
}
