<?php

namespace Tests\Unit\Nexus\Credit;

use App\Domains\Nexus\Credit\Domain\Entities\CreditTransaction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use PHPUnit\Framework\TestCase;

class CreditTransactionTest extends TestCase
{
    public function test_record_capturesAllFields(): void
    {
        $transaction = CreditTransaction::record(
            businessId: 1,
            type: CreditTransactionType::Deduction,
            amount: 20,
            reason: 'nexus.negotiation.propose',
            balanceAfter: 80,
            relatedId: 42,
        );

        $this->assertNull($transaction->id());
        $this->assertSame(1, $transaction->businessId());
        $this->assertSame(CreditTransactionType::Deduction, $transaction->type());
        $this->assertSame(20, $transaction->amount());
        $this->assertSame('nexus.negotiation.propose', $transaction->reason());
        $this->assertSame(80, $transaction->balanceAfter());
        $this->assertSame(42, $transaction->relatedId());
    }

    public function test_record_withoutRelatedId_isNull(): void
    {
        $transaction = CreditTransaction::record(
            businessId: 1,
            type: CreditTransactionType::AdminGrant,
            amount: 1000,
            reason: 'business.verified.starting_balance',
            balanceAfter: 1000,
        );

        $this->assertNull($transaction->relatedId());
    }
}
