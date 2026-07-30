<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_fromAmount_withValidData_setsAmountAndUppercasedCurrency(): void
    {
        $money = Money::fromAmount(1999, 'usd');

        $this->assertSame(1999, $money->amount());
        $this->assertSame('USD', $money->currency());
    }

    public function test_fromAmount_withNegativeAmount_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromAmount(-1, 'USD');
    }

    public function test_fromAmount_withInvalidCurrencyCode_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromAmount(100, 'DOLLARS');
    }

    public function test_equals_withSameAmountAndCurrency_returnsTrue(): void
    {
        $a = Money::fromAmount(500, 'USD');
        $b = Money::fromAmount(500, 'USD');

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_withDifferentCurrency_returnsFalse(): void
    {
        $a = Money::fromAmount(500, 'USD');
        $b = Money::fromAmount(500, 'EUR');

        $this->assertFalse($a->equals($b));
    }

    public function test_toString_formatsAsDecimalWithCurrency(): void
    {
        $money = Money::fromAmount(1999, 'USD');

        $this->assertSame('19.99 USD', (string) $money);
    }
}
