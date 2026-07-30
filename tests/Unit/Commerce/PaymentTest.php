<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Payment;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use App\Modules\Commerce\Domain\ValueObjects\PaymentStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    public function test_refund_onCompletedPayment_movesToRefunded(): void
    {
        $payment = Payment::record(1, 100, Money::fromAmount(1999, 'USD'), PaymentMethod::CreditCard, PaymentStatus::Completed, 'txn_1', []);

        $payment->refund();

        $this->assertSame(PaymentStatus::Refunded, $payment->status());
    }

    public function test_refund_onPendingPayment_throwsInvalidArgumentException(): void
    {
        $payment = Payment::record(1, 100, Money::fromAmount(1999, 'USD'), PaymentMethod::CreditCard, PaymentStatus::Pending, null, []);

        $this->expectException(InvalidArgumentException::class);

        $payment->refund();
    }

    public function test_refund_onAlreadyRefundedPayment_throwsInvalidArgumentException(): void
    {
        $payment = Payment::record(1, 100, Money::fromAmount(1999, 'USD'), PaymentMethod::CreditCard, PaymentStatus::Completed, 'txn_1', []);
        $payment->refund();

        $this->expectException(InvalidArgumentException::class);

        $payment->refund();
    }

    public function test_isCompleted_forCompletedStatus_returnsTrue(): void
    {
        $payment = Payment::record(1, 100, Money::fromAmount(1999, 'USD'), PaymentMethod::CreditCard, PaymentStatus::Completed, 'txn_1', []);

        $this->assertTrue($payment->isCompleted());
    }
}
