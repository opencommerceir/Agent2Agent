<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Order;
use App\Modules\Commerce\Domain\Entities\OrderItem;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\OrderNumber;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Order::refund() is new this stage (Checkout & Payment) — OrderTest
 * (Order Management stage) already covers confirm()/cancel()/
 * changeStatus() in isolation.
 */
class OrderRefundTest extends TestCase
{
    public function test_refund_fromConfirmed_movesToRefunded(): void
    {
        $order = $this->makeOrder();
        $order->confirm();

        $order->refund();

        $this->assertSame(OrderStatus::Refunded, $order->status());
    }

    public function test_refund_whenAlreadyRefunded_throwsInvalidArgumentException(): void
    {
        $order = $this->makeOrder();
        $order->refund();

        $this->expectException(InvalidArgumentException::class);

        $order->refund();
    }

    public function test_refund_whenAlreadyCancelled_throwsInvalidArgumentException(): void
    {
        $order = $this->makeOrder();
        $order->cancel();

        $this->expectException(InvalidArgumentException::class);

        $order->refund();
    }

    private function makeOrder(): Order
    {
        $item = OrderItem::create(100, new Quantity(2), Money::fromAmount(999, 'USD'));

        return Order::place(
            tenantId: 1,
            agentId: 1,
            orderNumber: new OrderNumber('ORD-20260730-00001'),
            items: [$item],
            subtotal: Money::fromAmount(1998, 'USD'),
            total: Money::fromAmount(1998, 'USD'),
        );
    }
}
