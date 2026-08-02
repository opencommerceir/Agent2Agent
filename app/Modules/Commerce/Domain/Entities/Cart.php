<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Domain\ValueObjects\CartStatus;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A shopping cart, owned by a User or Agent (MemberType — reused from
 * Core rather than a parallel Commerce enum, since "who owns this" is a
 * Core identity concept, not a Commerce one; Commerce depending on Core
 * is the allowed direction). In practice only Agent is populated end to
 * end today (MCP is the Agent entry point — MCPGatewayController's own
 * docblock), same as every other polymorphic member_type in this
 * codebase.
 *
 * addItem() is where the "one row per product per cart" rule
 * (CartItem Uniqueness) actually lives: adding a product already in the
 * cart increases its quantity instead of creating a second line.
 *
 * Since Phase 5, Stage 1 (Product Variants, §7.21), that identity is
 * really (productId, variantId) together — findItem() matches on both,
 * so two different variants of the same Product are always two separate
 * lines, never merged.
 */
final class Cart
{
    /**
     * @param list<CartItem> $items
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly MemberType $ownerType,
        private readonly int $ownerId,
        private CartStatus $status,
        private array $items,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function open(int $tenantId, MemberType $ownerType, int $ownerId): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            ownerType: $ownerType,
            ownerId: $ownerId,
            status: CartStatus::Active,
            items: [],
            createdAt: new DateTimeImmutable(),
        );
    }

    public function addItem(int $productId, Quantity $quantity, Money $unitPrice, ?int $variantId = null): void
    {
        $existing = $this->findItem($productId, $variantId);

        if ($existing) {
            $existing->increaseQuantity($quantity);

            return;
        }

        $this->items[] = CartItem::create($productId, $quantity, $unitPrice, $variantId);
    }

    public function removeItem(int $productId, ?int $variantId = null): CartItem
    {
        $item = $this->findItem($productId, $variantId);

        if (! $item) {
            throw new InvalidArgumentException("Product [{$productId}] is not in this cart.");
        }

        $this->items = array_values(array_filter(
            $this->items,
            fn (CartItem $cartItem) => ! ($cartItem->productId() === $productId && $cartItem->variantId() === $variantId),
        ));

        return $item;
    }

    public function findItem(int $productId, ?int $variantId = null): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->productId() === $productId && $item->variantId() === $variantId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<CartItem> the items that were cleared, so the caller
     *         can release each one's reserved inventory
     */
    public function clear(): array
    {
        $items = $this->items;
        $this->items = [];

        return $items;
    }

    public function markCheckedOut(): void
    {
        $this->status = CartStatus::CheckedOut;
    }

    public function abandon(): void
    {
        $this->status = CartStatus::Abandoned;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function ownerType(): MemberType
    {
        return $this->ownerType;
    }

    public function ownerId(): int
    {
        return $this->ownerId;
    }

    public function status(): CartStatus
    {
        return $this->status;
    }

    /**
     * @return list<CartItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function isActive(): bool
    {
        return $this->status === CartStatus::Active;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
