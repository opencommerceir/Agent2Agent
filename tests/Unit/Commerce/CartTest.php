<?php

namespace Tests\Unit\Commerce;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Domain\Entities\Cart;
use App\Modules\Commerce\Domain\ValueObjects\CartStatus;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pure Domain Entity tests — no Laravel bootstrap, no database.
 */
class CartTest extends TestCase
{
    public function test_open_startsActiveAndEmpty(): void
    {
        $cart = Cart::open(1, MemberType::Agent, 42);

        $this->assertNull($cart->id());
        $this->assertSame(CartStatus::Active, $cart->status());
        $this->assertTrue($cart->isActive());
        $this->assertSame([], $cart->items());
    }

    public function test_addItem_forNewProduct_appendsALine(): void
    {
        $cart = Cart::open(1, MemberType::Agent, 42);

        $cart->addItem(100, new Quantity(2), Money::fromAmount(999, 'USD'));

        $this->assertCount(1, $cart->items());
        $this->assertSame(2, $cart->findItem(100)->quantity()->value());
    }

    public function test_addItem_forAlreadyPresentProduct_increasesQuantityInsteadOfDuplicatingLine(): void
    {
        $cart = Cart::open(1, MemberType::Agent, 42);

        $cart->addItem(100, new Quantity(2), Money::fromAmount(999, 'USD'));
        $cart->addItem(100, new Quantity(3), Money::fromAmount(999, 'USD'));

        $this->assertCount(1, $cart->items());
        $this->assertSame(5, $cart->findItem(100)->quantity()->value());
    }

    public function test_removeItem_removesTheMatchingLineAndReturnsIt(): void
    {
        $cart = Cart::open(1, MemberType::Agent, 42);
        $cart->addItem(100, new Quantity(2), Money::fromAmount(999, 'USD'));

        $removed = $cart->removeItem(100);

        $this->assertSame(100, $removed->productId());
        $this->assertCount(0, $cart->items());
    }

    public function test_removeItem_forProductNotInCart_throwsInvalidArgumentException(): void
    {
        $cart = Cart::open(1, MemberType::Agent, 42);

        $this->expectException(InvalidArgumentException::class);

        $cart->removeItem(999);
    }

    public function test_clear_emptiesCartAndReturnsThePreviousItems(): void
    {
        $cart = Cart::open(1, MemberType::Agent, 42);
        $cart->addItem(100, new Quantity(2), Money::fromAmount(999, 'USD'));
        $cart->addItem(200, new Quantity(1), Money::fromAmount(500, 'USD'));

        $cleared = $cart->clear();

        $this->assertCount(2, $cleared);
        $this->assertCount(0, $cart->items());
    }

    public function test_markCheckedOut_changesStatus(): void
    {
        $cart = Cart::open(1, MemberType::Agent, 42);

        $cart->markCheckedOut();

        $this->assertSame(CartStatus::CheckedOut, $cart->status());
        $this->assertFalse($cart->isActive());
    }
}
