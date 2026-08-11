<?php

namespace Tests\Unit\Nexus\Catalog;

use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_fromAmount_withValidData_normalizesCurrencyToUppercase(): void
    {
        $money = Money::fromAmount(150000, 'irt');

        $this->assertSame(150000, $money->amount());
        $this->assertSame('IRT', $money->currency());
    }

    public function test_fromAmount_withNegativeAmount_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromAmount(-1, 'IRT');
    }

    public function test_fromAmount_withInvalidCurrency_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromAmount(100, 'toman');
    }

    public function test_equals_comparesAmountAndCurrency(): void
    {
        $a = Money::fromAmount(1000, 'IRT');
        $b = Money::fromAmount(1000, 'IRT');
        $c = Money::fromAmount(1000, 'USD');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
