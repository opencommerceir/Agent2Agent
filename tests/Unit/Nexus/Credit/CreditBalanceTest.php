<?php

namespace Tests\Unit\Nexus\Credit;

use App\Domains\Nexus\Credit\Domain\Entities\CreditBalance;
use App\Domains\Nexus\Credit\Domain\Exceptions\InsufficientCreditException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CreditBalanceTest extends TestCase
{
    public function test_open_withNoStartingBalance_isZero(): void
    {
        $balance = CreditBalance::open(businessId: 1);

        $this->assertNull($balance->id());
        $this->assertSame(1, $balance->businessId());
        $this->assertSame(0, $balance->balance());
    }

    public function test_open_withStartingBalance_setsIt(): void
    {
        $balance = CreditBalance::open(businessId: 1, startingBalance: 500);

        $this->assertSame(500, $balance->balance());
    }

    public function test_credit_increasesBalance(): void
    {
        $balance = CreditBalance::open(businessId: 1, startingBalance: 100);

        $balance->credit(50);

        $this->assertSame(150, $balance->balance());
    }

    public function test_credit_withNonPositiveAmount_throws(): void
    {
        $balance = CreditBalance::open(businessId: 1);

        $this->expectException(InvalidArgumentException::class);

        $balance->credit(0);
    }

    public function test_debit_decreasesBalance(): void
    {
        $balance = CreditBalance::open(businessId: 1, startingBalance: 100);

        $balance->debit(30);

        $this->assertSame(70, $balance->balance());
    }

    public function test_debit_exactlyTheBalance_leavesZero(): void
    {
        $balance = CreditBalance::open(businessId: 1, startingBalance: 30);

        $balance->debit(30);

        $this->assertSame(0, $balance->balance());
    }

    public function test_debit_moreThanBalance_throwsInsufficientCredit(): void
    {
        $balance = CreditBalance::open(businessId: 1, startingBalance: 10);

        $this->expectException(InsufficientCreditException::class);

        $balance->debit(11);
    }

    public function test_debit_withNonPositiveAmount_throws(): void
    {
        $balance = CreditBalance::open(businessId: 1, startingBalance: 10);

        $this->expectException(InvalidArgumentException::class);

        $balance->debit(0);
    }
}
