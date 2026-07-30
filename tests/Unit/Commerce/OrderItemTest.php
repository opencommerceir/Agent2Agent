<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\CartItem;
use App\Modules\Commerce\Domain\Entities\OrderItem;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

class OrderItemTest extends TestCase
{
    public function test_create_setsProductQuantityAndUnitPrice(): void
    {
        $item = OrderItem::create(100, new Quantity(2), Money::fromAmount(999, 'USD'));

        $this->assertSame(100, $item->productId());
        $this->assertSame(2, $item->quantity()->value());
        $this->assertSame(1998, $item->totalAmount());
    }

    public function test_fromCartItem_copiesTheCartItemsSnapshotPrice(): void
    {
        $cartItem = CartItem::create(100, new Quantity(3), Money::fromAmount(500, 'USD'));

        $orderItem = OrderItem::fromCartItem($cartItem);

        $this->assertSame(100, $orderItem->productId());
        $this->assertSame(3, $orderItem->quantity()->value());
        $this->assertSame(500, $orderItem->unitPrice()->amount());
        $this->assertSame(1500, $orderItem->totalAmount());
    }
}
