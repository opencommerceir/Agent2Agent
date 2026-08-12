<?php

namespace Tests\Unit\Nexus\Negotiation;

use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NegotiationTermsTest extends TestCase
{
    public function test_totalAmount_multipliesPriceByQuantity(): void
    {
        $terms = new NegotiationTerms(Money::fromAmount(10000, 'IRT'), 3, null);

        $this->assertSame(30000, $terms->totalAmount());
    }

    public function test_constructor_withZeroQuantity_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NegotiationTerms(Money::fromAmount(10000, 'IRT'), 0, null);
    }

    public function test_toArray_andFromArray_roundTrip(): void
    {
        $terms = new NegotiationTerms(Money::fromAmount(5000, 'IRT'), 2, 'bulk order');

        $restored = NegotiationTerms::fromArray($terms->toArray());

        $this->assertTrue($terms->price()->equals($restored->price()));
        $this->assertSame($terms->quantity(), $restored->quantity());
        $this->assertSame($terms->notes(), $restored->notes());
    }

    public function test_money_fromAmount_withNegativeAmount_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromAmount(-1, 'IRT');
    }

    public function test_money_equals_comparesAmountAndCurrency(): void
    {
        $a = Money::fromAmount(1000, 'irt');
        $b = Money::fromAmount(1000, 'IRT');

        $this->assertTrue($a->equals($b));
    }
}
