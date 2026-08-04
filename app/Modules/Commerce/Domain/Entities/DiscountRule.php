<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\DiscountPriority;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Stackability;
use DateTimeImmutable;

/**
 * A tenant-defined, reusable promotional rule — evaluated automatically
 * against a Cart (`DiscountRuleEvaluator`, no code required) or reached
 * explicitly through a linked Coupon (`Coupon::$discountRuleId`, §7.24's
 * own Coupon widening). `conditions` is frozen at creation, the same
 * "structure fixed, generic fields aren't" shape `VariantAttribute`'s own
 * values already have (§7.21) — there is no "add a condition to an
 * existing rule" operation this stage, only name/description/
 * discountValue/priority/stackability/startsAt/expiresAt/isActive are
 * editable via `update()`.
 */
final class DiscountRule
{
    /**
     * @param list<DiscountRuleCondition> $conditions
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private string $name,
        private ?string $description,
        private readonly DiscountType $discountType,
        private int $discountValue,
        private DiscountPriority $priority,
        private Stackability $stackability,
        private readonly array $conditions,
        private DateTimeImmutable $startsAt,
        private ?DateTimeImmutable $expiresAt,
        private bool $isActive,
        private readonly ?int $maxUses,
        private int $usedCount,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param list<DiscountRuleCondition> $conditions
     */
    public static function create(
        int $tenantId,
        string $name,
        ?string $description,
        DiscountType $discountType,
        int $discountValue,
        DiscountPriority $priority,
        Stackability $stackability,
        array $conditions,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $expiresAt = null,
        ?int $maxUses = null,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            description: $description,
            discountType: $discountType,
            discountValue: $discountValue,
            priority: $priority,
            stackability: $stackability,
            conditions: $conditions,
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            isActive: true,
            maxUses: $maxUses,
            usedCount: 0,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function update(
        string $name,
        ?string $description,
        int $discountValue,
        DiscountPriority $priority,
        Stackability $stackability,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $expiresAt,
        bool $isActive,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->discountValue = $discountValue;
        $this->priority = $priority;
        $this->stackability = $stackability;
        $this->startsAt = $startsAt;
        $this->expiresAt = $expiresAt;
        $this->isActive = $isActive;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function recordUsage(): void
    {
        $this->usedCount++;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function hasReachedMaxUses(): bool
    {
        return $this->maxUses !== null && $this->usedCount >= $this->maxUses;
    }

    /**
     * The single, on-demand "is this rule usable right now" check —
     * mirrors `Coupon::isExpired()`'s own live-checked-at-evaluation-time
     * shape exactly rather than a scheduled batch job that flips
     * `isActive` in the background (no such job exists for Coupon either,
     * and rule §د.5's own "expired rules stop applying" requirement is
     * fully satisfied by checking this at evaluation time — see §7.24's
     * own note on why no `commerce:expire-discount-rules` command was
     * built this stage).
     */
    public function isCurrentlyActive(DateTimeImmutable $now): bool
    {
        if (! $this->isActive || $this->hasReachedMaxUses()) {
            return false;
        }

        if ($now < $this->startsAt) {
            return false;
        }

        return $this->expiresAt === null || $now <= $this->expiresAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function discountType(): DiscountType
    {
        return $this->discountType;
    }

    public function discountValue(): int
    {
        return $this->discountValue;
    }

    public function priority(): DiscountPriority
    {
        return $this->priority;
    }

    public function stackability(): Stackability
    {
        return $this->stackability;
    }

    /**
     * @return list<DiscountRuleCondition>
     */
    public function conditions(): array
    {
        return $this->conditions;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function maxUses(): ?int
    {
        return $this->maxUses;
    }

    public function usedCount(): int
    {
        return $this->usedCount;
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
