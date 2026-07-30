<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\Order;
use App\Modules\Commerce\Domain\Entities\OrderItem;
use App\Modules\Commerce\Domain\Exceptions\InvalidOrderStatusException;
use App\Modules\Commerce\Domain\Exceptions\OrderAlreadyCancelledException;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\OrderNumber;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database.
 */
class OrderTest extends TestCase
{
    public function test_place_startsPendingWithFrozenItems(): void
    {
        $order = $this->makeOrder();

        $this->assertNull($order->id());
        $this->assertSame(OrderStatus::Pending, $order->status());
        $this->assertCount(1, $order->items());
    }

    public function test_confirm_movesToConfirmed(): void
    {
        $order = $this->makeOrder();

        $order->confirm();

        $this->assertSame(OrderStatus::Confirmed, $order->status());
    }

    public function test_cancel_fromPending_movesToCancelled(): void
    {
        $order = $this->makeOrder();

        $order->cancel();

        $this->assertSame(OrderStatus::Cancelled, $order->status());
        $this->assertFalse($order->isCancellable());
    }

    public function test_cancel_fromConfirmed_movesToCancelled(): void
    {
        $order = $this->makeOrder();
        $order->confirm();

        $order->cancel();

        $this->assertSame(OrderStatus::Cancelled, $order->status());
    }

    public function test_cancel_whenAlreadyCancelled_throwsOrderAlreadyCancelledException(): void
    {
        $order = $this->makeOrder();
        $order->cancel();

        $this->expectException(OrderAlreadyCancelledException::class);

        $order->cancel();
    }

    public function test_cancel_afterShipped_throwsInvalidOrderStatusException(): void
    {
        $order = $this->makeOrder();
        $order->confirm();
        $order->changeStatus(OrderStatus::Processing);
        $order->changeStatus(OrderStatus::Shipped);

        $this->expectException(InvalidOrderStatusException::class);

        $order->cancel();
    }

    public function test_changeStatus_toCancelled_throwsInvalidOrderStatusException(): void
    {
        $order = $this->makeOrder();

        $this->expectException(InvalidOrderStatusException::class);

        $order->changeStatus(OrderStatus::Cancelled);
    }

    public function test_changeStatus_afterDelivered_throwsInvalidOrderStatusException(): void
    {
        $order = $this->makeOrder();
        $order->confirm();
        $order->changeStatus(OrderStatus::Processing);
        $order->changeStatus(OrderStatus::Shipped);
        $order->changeStatus(OrderStatus::Delivered);

        $this->expectException(InvalidOrderStatusException::class);

        $order->changeStatus(OrderStatus::Processing);
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
