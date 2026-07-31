<?php

namespace App\Modules\Loyalty\Domain\Entities;

use App\Modules\Loyalty\Domain\ValueObjects\Points;
use App\Modules\Loyalty\Domain\ValueObjects\RewardType;
use DateTimeImmutable;

/**
 * Something a Customer can spend points on. `discount_amount` (cents,
 * Money-as-Integer) only has meaning when `reward_type` is
 * `discount_coupon` — mirrors Commerce's own Coupon docblock's warning
 * about `discount_value` meaning two different things depending on
 * `discount_type`, except Reward keeps discount_amount nullable rather
 * than reusing it for a second purpose.
 *
 * No update/deactivate method exists this stage — not requested, and
 * `is_active` is set once at creation and read by ListRewardsAction's own
 * filter; a future stage that needs to retire a Reward without deleting
 * it would add that as its own deliberate operation (same reasoning
 * Workflow's rules/actions are frozen and only name/description/status
 * are editable through a dedicated Action).
 */
final class Reward
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly string $name,
        private readonly ?string $description,
        private readonly RewardType $rewardType,
        private readonly Points $pointsRequired,
        private readonly ?int $discountAmount,
        private readonly bool $isActive,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $tenantId,
        string $name,
        ?string $description,
        RewardType $rewardType,
        Points $pointsRequired,
        ?int $discountAmount = null,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            description: $description,
            rewardType: $rewardType,
            pointsRequired: $pointsRequired,
            discountAmount: $discountAmount,
            isActive: true,
            createdAt: new DateTimeImmutable(),
        );
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

    public function rewardType(): RewardType
    {
        return $this->rewardType;
    }

    public function pointsRequired(): Points
    {
        return $this->pointsRequired;
    }

    public function discountAmount(): ?int
    {
        return $this->discountAmount;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
