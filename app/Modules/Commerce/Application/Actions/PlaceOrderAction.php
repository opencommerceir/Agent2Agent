<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Domain\Entities\Order;
use App\Modules\Commerce\Domain\Entities\OrderItem;
use App\Modules\Commerce\Domain\Events\OrderWasConfirmed;
use App\Modules\Commerce\Domain\Events\OrderWasPlaced;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\OrderNumber;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use RuntimeException;

/**
 * Converts the calling Agent's own Cart into a placed, immediately
 * confirmed Order (Order::confirm()'s docblock explains why there is no
 * separate confirmation step yet).
 *
 * Ownership is re-verified explicitly here (cartId belongs to *this*
 * tenant *and* this* agent) rather than trusting whatever cart_id the
 * caller supplies — the same CartNotFoundException a genuinely
 * nonexistent cart_id would produce, so a mismatched id never reveals
 * "this cart exists, just not yours".
 *
 * Inventory handling is the two-phase model Inventory::commit()
 * documents: every item's quantity was already soft-reserved when it was
 * added to the Cart; placing the Order converts that hold into an actual
 * stock reduction. CheckInventoryAction re-validates first as a guard
 * against inventory having been adjusted downward since the Cart was
 * built (e.g. a manual correction) — defense in depth, same reasoning as
 * AddToCartAction.
 *
 * The whole operation is one DB transaction: Cart, Inventory and Order
 * all change together or not at all.
 */
final class PlaceOrderAction
{
    private const MAX_ORDER_NUMBER_ATTEMPTS = 5;

    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly OrderRepositoryInterface $orders,
        private readonly InventoryRepositoryInterface $inventories,
        private readonly CheckInventoryAction $checkInventory,
    ) {
    }

    public function execute(int $tenantId, int $agentId, int $cartId, ?string $notes = null): OrderData
    {
        return DB::transaction(function () use ($tenantId, $agentId, $cartId, $notes) {
            $cart = $this->carts->findById($cartId, $tenantId);

            if (! $cart || $cart->ownerType() !== MemberType::Agent || $cart->ownerId() !== $agentId) {
                throw new CartNotFoundException("Cart [{$cartId}] does not exist.");
            }

            if (! $cart->isActive() || $cart->items() === []) {
                throw new InvalidArgumentException('Cart is empty or not active.');
            }

            $orderItems = [];

            foreach ($cart->items() as $cartItem) {
                $this->checkInventory->authorize($cartItem->productId(), $tenantId, $cartItem->quantity());
                $orderItems[] = OrderItem::fromCartItem($cartItem);
            }

            foreach ($orderItems as $orderItem) {
                $inventory = $this->inventories->findByProduct($orderItem->productId(), $tenantId);
                $inventory->commit($orderItem->quantity());
                $this->inventories->save($inventory);
            }

            $currency = $orderItems[0]->unitPrice()->currency();
            $subtotalAmount = array_sum(array_map(fn (OrderItem $item) => $item->totalAmount(), $orderItems));
            $subtotal = Money::fromAmount($subtotalAmount, $currency);
            $total = $subtotal; // no tax/shipping/discount logic this phase — deliberately out of scope

            $orderNumber = $this->generateUniqueOrderNumber($tenantId);

            $order = Order::place($tenantId, $agentId, $orderNumber, $orderItems, $subtotal, $total, $notes);
            $order = $this->orders->save($order);

            Event::dispatch(new OrderWasPlaced($order));

            $order->confirm();
            $order = $this->orders->save($order);

            Event::dispatch(new OrderWasConfirmed($order));

            $cart->clear();
            $cart->markCheckedOut();
            $this->carts->save($cart);

            return OrderData::fromEntity($order);
        });
    }

    private function generateUniqueOrderNumber(int $tenantId): OrderNumber
    {
        $today = new DateTimeImmutable();

        for ($attempt = 0; $attempt < self::MAX_ORDER_NUMBER_ATTEMPTS; $attempt++) {
            $orderNumber = OrderNumber::generate($today, random_int(1, 99999));

            if (! $this->orders->orderNumberExists($orderNumber->value(), $tenantId)) {
                return $orderNumber;
            }
        }

        throw new RuntimeException('Could not generate a unique order number after several attempts.');
    }
}
