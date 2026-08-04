<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionInvoiceStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SubscriptionInvoiceTest extends TestCase
{
    private function makeInvoice(): SubscriptionInvoice
    {
        return SubscriptionInvoice::create(1, 100, Money::fromAmount(5000, 'USD'), new DateTimeImmutable());
    }

    public function test_create_startsPendingWithZeroRetries(): void
    {
        $invoice = $this->makeInvoice();

        $this->assertSame(SubscriptionInvoiceStatus::Pending, $invoice->status());
        $this->assertSame(0, $invoice->retryCount());
        $this->assertNull($invoice->orderId());
    }

    public function test_markPaid_setsStatusAndPaidAt(): void
    {
        $invoice = $this->makeInvoice();

        $invoice->markPaid();

        $this->assertSame(SubscriptionInvoiceStatus::Paid, $invoice->status());
        $this->assertNotNull($invoice->paidAt());
    }

    public function test_markFailed_incrementsRetryCountAndSetsFailedAt(): void
    {
        $invoice = $this->makeInvoice();

        $invoice->markFailed();

        $this->assertSame(SubscriptionInvoiceStatus::Failed, $invoice->status());
        $this->assertSame(1, $invoice->retryCount());
        $this->assertNotNull($invoice->failedAt());
    }

    public function test_hasExhaustedRetries_isFalseUnderThreeFailures(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->markFailed();
        $invoice->markFailed();

        $this->assertFalse($invoice->hasExhaustedRetries());
    }

    public function test_hasExhaustedRetries_isTrueAtThreeFailures(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->markFailed();
        $invoice->markFailed();
        $invoice->markFailed();

        $this->assertTrue($invoice->hasExhaustedRetries());
    }

    public function test_isRetryDue_beforeIntervalElapsed_isFalse(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->markFailed();

        $this->assertFalse($invoice->isRetryDue(new DateTimeImmutable('+1 day')));
    }

    public function test_isRetryDue_afterIntervalElapsed_isTrue(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->markFailed();

        $this->assertTrue($invoice->isRetryDue(new DateTimeImmutable('+4 days')));
    }

    public function test_isRetryDue_whenNotFailed_isFalse(): void
    {
        $invoice = $this->makeInvoice();

        $this->assertFalse($invoice->isRetryDue(new DateTimeImmutable('+10 days')));
    }

    public function test_isRetryDue_whenRetriesExhausted_isFalseEvenAfterInterval(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->markFailed();
        $invoice->markFailed();
        $invoice->markFailed();

        $this->assertFalse($invoice->isRetryDue(new DateTimeImmutable('+30 days')));
    }
}
