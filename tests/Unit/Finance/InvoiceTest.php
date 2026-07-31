<?php

namespace Tests\Unit\Finance;

use App\Modules\Finance\Domain\Entities\Invoice;
use App\Modules\Finance\Domain\Entities\InvoiceItem;
use App\Modules\Finance\Domain\ValueObjects\InvoiceNumber;
use App\Modules\Finance\Domain\ValueObjects\InvoiceStatus;
use App\Modules\Finance\Domain\ValueObjects\Money;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    private function makeInvoice(): Invoice
    {
        return Invoice::create(
            tenantId: 1,
            orderId: 10,
            customerId: 20,
            invoiceNumber: InvoiceNumber::generate(new DateTimeImmutable('2026-07-31'), 1),
            items: [InvoiceItem::create('Widget', 2, Money::fromAmount(5000, 'USD'), Money::fromAmount(10000, 'USD'))],
            subtotal: Money::fromAmount(10000, 'USD'),
            tax: Money::fromAmount(850, 'USD'),
            total: Money::fromAmount(10850, 'USD'),
        );
    }

    public function test_create_startsAsDraftWithNoIssuedAt(): void
    {
        $invoice = $this->makeInvoice();

        $this->assertSame(InvoiceStatus::Draft, $invoice->status());
        $this->assertNull($invoice->issuedAt());
        $this->assertCount(1, $invoice->items());
    }

    public function test_issue_fromDraft_setsIssuedStatusAndIssuedAt(): void
    {
        $invoice = $this->makeInvoice();

        $invoice->issue();

        $this->assertSame(InvoiceStatus::Issued, $invoice->status());
        $this->assertNotNull($invoice->issuedAt());
    }

    public function test_issue_calledTwice_throwsInvalidArgumentException(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->issue();

        $this->expectException(InvalidArgumentException::class);

        $invoice->issue();
    }
}
