<?php

namespace Tests\Unit\Loyalty;

use App\Modules\Loyalty\Domain\Entities\PointTransaction;
use App\Modules\Loyalty\Domain\Exceptions\InvalidPointsException;
use App\Modules\Loyalty\Domain\ValueObjects\TransactionType;
use PHPUnit\Framework\TestCase;

class PointTransactionTest extends TestCase
{
    public function test_record_earn_withPositivePoints_succeeds(): void
    {
        $transaction = PointTransaction::record(1, 1, 150, TransactionType::Earn, 'Order #1');

        $this->assertSame(150, $transaction->points());
        $this->assertSame(TransactionType::Earn, $transaction->transactionType());
    }

    public function test_record_earn_withNegativePoints_throwsInvalidPointsException(): void
    {
        $this->expectException(InvalidPointsException::class);

        PointTransaction::record(1, 1, -150, TransactionType::Earn);
    }

    public function test_record_redeem_withNegativePoints_succeeds(): void
    {
        $transaction = PointTransaction::record(1, 1, -100, TransactionType::Redeem);

        $this->assertSame(-100, $transaction->points());
    }

    public function test_record_redeem_withPositivePoints_throwsInvalidPointsException(): void
    {
        $this->expectException(InvalidPointsException::class);

        PointTransaction::record(1, 1, 100, TransactionType::Redeem);
    }

    public function test_record_withZeroPoints_throwsInvalidPointsException(): void
    {
        $this->expectException(InvalidPointsException::class);

        PointTransaction::record(1, 1, 0, TransactionType::Adjust);
    }

    public function test_record_adjust_allowsEitherSign(): void
    {
        $positive = PointTransaction::record(1, 1, 20, TransactionType::Adjust);
        $negative = PointTransaction::record(1, 1, -20, TransactionType::Adjust);

        $this->assertSame(20, $positive->points());
        $this->assertSame(-20, $negative->points());
    }

    public function test_record_storesReferenceIdAndExpiresAt(): void
    {
        $expiresAt = new \DateTimeImmutable('2027-01-01');

        $transaction = PointTransaction::record(1, 1, 100, TransactionType::Earn, 'desc', 42, $expiresAt);

        $this->assertSame(42, $transaction->referenceId());
        $this->assertSame($expiresAt, $transaction->expiresAt());
    }
}
