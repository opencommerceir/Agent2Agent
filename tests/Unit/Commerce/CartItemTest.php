<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Domain\Entities\CartItem;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

class CartItemTest extends TestCase
{
    public function test_create_setsProductQuantityAndUnitPrice(): void
    {
        $item = CartItem::create(100, new Quantity(2), Money::fromAmount(999, 'USD'));

        $this->assertSame(100, $item->productId());
        $this->assertSame(2, $item->quantity()->value());
        $this->assertSame(999, $item->unitPrice()->amount());
    }

    public function test_subtotalAmount_multipliesQuantityByUnitPrice(): void
    {
        $item = CartItem::create(100, new Quantity(3), Money::fromAmount(500, 'USD'));

        $this->assertSame(1500, $item->subtotalAmount());
    }

    public function test_increaseQuantity_addsToExistingQuantity(): void
    {
        $item = CartItem::create(100, new Quantity(2), Money::fromAmount(500, 'USD'));

        $item->increaseQuantity(new Quantity(3));

        $this->assertSame(5, $item->quantity()->value());
    }

    public function test_changeQuantity_replacesQuantity(): void
    {
        $item = CartItem::create(100, new Quantity(2), Money::fromAmount(500, 'USD'));

        $item->changeQuantity(new Quantity(7));

        $this->assertSame(7, $item->quantity()->value());
    }
}
