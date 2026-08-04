<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\BillingCycle;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\TrialPeriod;
use DateTimeImmutable;

/**
 * A tenant-defined, sellable recurring plan (e.g. "Pro Monthly"). No
 * update() this stage — only Create/Get/List were requested for Plans
 * (§7.25), the same "structure frozen, not requested" shape
 * `ShippingMethod` already has. `billingCycle` is immutable after creation
 * for the same reason `SKU`/a Category's slug are: it's the plan's own
 * business identity for how it bills, not a cosmetic field.
 */
final class SubscriptionPlan
{
    /**
     * @param array<int, string> $features
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly string $name,
        private readonly ?string $description,
        private readonly BillingCycle $billingCycle,
        private readonly Money $price,
        private readonly TrialPeriod $trialPeriod,
        private readonly array $features,
        private readonly bool $isActive,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param array<int, string> $features
     */
    public static function create(
        int $tenantId,
        string $name,
        ?string $description,
        BillingCycle $billingCycle,
        Money $price,
        int $trialDays = 0,
        array $features = [],
        bool $isActive = true,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            description: $description,
            billingCycle: $billingCycle,
            price: $price,
            trialPeriod: new TrialPeriod($trialDays),
            features: $features,
            isActive: $isActive,
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

    public function billingCycle(): BillingCycle
    {
        return $this->billingCycle;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function trialPeriod(): TrialPeriod
    {
        return $this->trialPeriod;
    }

    public function hasTrial(): bool
    {
        return $this->trialPeriod->hasTrial();
    }

    /**
     * @return array<int, string>
     */
    public function features(): array
    {
        return $this->features;
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
