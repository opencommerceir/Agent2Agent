<?php

namespace Tests\Unit\Nexus\Credit;

use App\Domains\Nexus\Credit\Domain\Entities\HoldingCreditPool;
use App\Domains\Nexus\Credit\Domain\Exceptions\InsufficientCreditException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HoldingCreditPoolTest extends TestCase
{
    public function test_open_withNoStartingBalance_isZero(): void
    {
        $pool = HoldingCreditPool::open(holdingId: 1);

        $this->assertNull($pool->id());
        $this->assertSame(1, $pool->holdingId());
        $this->assertSame(0, $pool->balance());
    }

    public function test_credit_increasesBalance(): void
    {
        $pool = HoldingCreditPool::open(holdingId: 1, startingBalance: 100);

        $pool->credit(50);

        $this->assertSame(150, $pool->balance());
    }

    public function test_debit_decreasesBalance(): void
    {
        $pool = HoldingCreditPool::open(holdingId: 1, startingBalance: 100);

        $pool->debit(30);

        $this->assertSame(70, $pool->balance());
    }

    public function test_debit_moreThanBalance_throwsInsufficientCredit(): void
    {
        $pool = HoldingCreditPool::open(holdingId: 1, startingBalance: 10);

        $this->expectException(InsufficientCreditException::class);

        $pool->debit(11);
    }

    public function test_debit_withNonPositiveAmount_throws(): void
    {
        $pool = HoldingCreditPool::open(holdingId: 1, startingBalance: 10);

        $this->expectException(InvalidArgumentException::class);

        $pool->debit(0);
    }
}
