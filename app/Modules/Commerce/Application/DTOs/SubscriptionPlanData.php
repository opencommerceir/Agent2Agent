<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\SubscriptionPlan;

final class SubscriptionPlanData
{
    /**
     * @param array<int, string> $features
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $billingCycle,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
        public readonly int $trialDays,
        public readonly array $features,
        public readonly bool $isActive,
    ) {
    }

    public static function fromEntity(SubscriptionPlan $plan): self
    {
        return new self(
            id: $plan->id(),
            tenantId: $plan->tenantId(),
            name: $plan->name(),
            description: $plan->description(),
            billingCycle: $plan->billingCycle()->value,
            priceAmount: $plan->price()->amount(),
            priceCurrency: $plan->price()->currency(),
            trialDays: $plan->trialPeriod()->days(),
            features: $plan->features(),
            isActive: $plan->isActive(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'name' => $this->name,
            'description' => $this->description,
            'billingCycle' => $this->billingCycle,
            'priceAmount' => $this->priceAmount,
            'priceCurrency' => $this->priceCurrency,
            'trialDays' => $this->trialDays,
            'features' => $this->features,
            'isActive' => $this->isActive,
        ];
    }
}
