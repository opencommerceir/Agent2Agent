<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\VariantSKU;
use DateTimeImmutable;

/**
 * One concrete, sellable combination of a Product's own variant
 * attributes (e.g. Color=Red, Size=L on a T-Shirt). `sku` is readonly —
 * a variant's SKU is its business identity, the same "not updatable"
 * rule Product's own SKU already has (UpdateProductAction's docblock);
 * `attributes` (the combination itself) is readonly too, for the same
 * reason — changing what combination a variant *represents* would really
 * be a different variant, not an update to this one.
 *
 * Deliberately carries no stock quantity of its own — see this stage's
 * `create_product_variants_table` migration docblock for the full
 * reasoning: stock lives in the existing `inventories` table (a
 * `variant_id`-keyed row, reusing Inventory's own reserve/commit
 * lifecycle), not a second, independent mechanism on this entity.
 */
final class ProductVariant
{
    /**
     * @param array<string, string> $attributes
     */
    private function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $productId,
        private readonly VariantSKU $sku,
        private Money $price,
        private readonly array $attributes,
        private ?string $imageUrl,
        private bool $isActive,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<string, string> $attributes
     */
    public static function create(
        int $tenantId,
        int $productId,
        VariantSKU $sku,
        Money $price,
        array $attributes,
        ?string $imageUrl = null,
        bool $isActive = true,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            tenantId: $tenantId,
            productId: $productId,
            sku: $sku,
            price: $price,
            attributes: $attributes,
            imageUrl: $imageUrl,
            isActive: $isActive,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @param array<string, string> $attributes
     */
    public static function reconstitute(
        ?int $id,
        int $tenantId,
        int $productId,
        VariantSKU $sku,
        Money $price,
        array $attributes,
        ?string $imageUrl,
        bool $isActive,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $tenantId, $productId, $sku, $price, $attributes, $imageUrl, $isActive, $createdAt, $updatedAt);
    }

    public function update(Money $price, ?string $imageUrl, bool $isActive): void
    {
        $this->price = $price;
        $this->imageUrl = $imageUrl;
        $this->isActive = $isActive;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function sku(): VariantSKU
    {
        return $this->sku;
    }

    public function price(): Money
    {
        return $this->price;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function imageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
