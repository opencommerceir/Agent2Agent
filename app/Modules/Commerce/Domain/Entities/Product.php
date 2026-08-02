<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;
use App\Modules\Commerce\Domain\ValueObjects\SKU;
use DateTimeImmutable;

/**
 * A Product native to this platform's own catalog — distinct from
 * Domain\UCP\UCPProduct, which is a framework-free *snapshot* shape an
 * external Connector normalizes data into. This entity is what Commerce
 * itself persists and owns; UCP is what gets handed to/from other
 * systems. SKU is immutable after creation (it is the product's business
 * identity); category, pricing, status and attributes may change via
 * update().
 */
final class Product
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private ?int $categoryId,
        private string $name,
        private readonly string $slug,
        private ?string $description,
        private readonly SKU $sku,
        private Money $price,
        private ProductStatus $status,
        private array $attributes,
        private readonly DateTimeImmutable $createdAt,
        private bool $isParent = false,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(
        int $tenantId,
        ?int $categoryId,
        string $name,
        string $slug,
        ?string $description,
        SKU $sku,
        Money $price,
        ProductStatus $status = ProductStatus::Draft,
        array $attributes = [],
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            categoryId: $categoryId,
            name: $name,
            slug: $slug,
            description: $description,
            sku: $sku,
            price: $price,
            status: $status,
            attributes: $attributes,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(
        ?int $categoryId,
        string $name,
        ?string $description,
        Money $price,
        ProductStatus $status,
        array $attributes,
    ): void {
        $this->categoryId = $categoryId;
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->status = $status;
        $this->attributes = $attributes;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function categoryId(): ?int
    {
        return $this->categoryId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function sku(): SKU
    {
        return $this->sku;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function status(): ProductStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->status === ProductStatus::Active;
    }

    /**
     * A denormalized convenience flag, not the source of truth for
     * "does this Product have variants" — see this stage's own
     * `add_is_parent_to_products_table` migration docblock (Phase 5,
     * Stage 1, §7.21) for the full reasoning and its known drift risk.
     */
    public function isParent(): bool
    {
        return $this->isParent;
    }

    public function markAsParent(): void
    {
        $this->isParent = true;
    }
}
