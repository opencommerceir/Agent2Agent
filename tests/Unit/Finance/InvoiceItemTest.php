<?php

namespace Tests\Unit\Finance;

use App\Modules\Finance\Domain\Entities\InvoiceItem;
use App\Modules\Finance\Domain\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class InvoiceItemTest extends TestCase
{
    public function test_create_setsAllFields(): void
    {
        $item = InvoiceItem::create('Widget', 2, Money::fromAmount(5000, 'USD'), Money::fromAmount(10000, 'USD'));

        $this->assertSame('Widget', $item->description());
        $this->assertSame(2, $item->quantity());
        $this->assertSame(5000, $item->unitPrice()->amount());
        $this->assertSame(10000, $item->totalAmount()->amount());
    }
}
