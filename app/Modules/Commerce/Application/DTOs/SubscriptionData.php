<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\Subscription;

final class SubscriptionData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $customerId,
        public readonly int $subscriptionPlanId,
        public readonly string $status,
        public readonly string $currentPeriodStart,
        public readonly string $currentPeriodEnd,
        public readonly ?string $trialStart,
        public readonly ?string $trialEnd,
        public readonly ?string $pausedAt,
        public readonly ?string $cancelledAt,
        public readonly bool $cancelAtPeriodEnd,
        public readonly ?string $paymentMethodId,
    ) {
    }

    public static function fromEntity(Subscription $subscription): self
    {
        return new self(
            id: $subscription->id(),
            tenantId: $subscription->tenantId(),
            customerId: $subscription->customerId(),
            subscriptionPlanId: $subscription->subscriptionPlanId(),
            status: $subscription->status()->value,
            currentPeriodStart: $subscription->currentPeriodStart()->format(DATE_ATOM),
            currentPeriodEnd: $subscription->currentPeriodEnd()->format(DATE_ATOM),
            trialStart: $subscription->trialStart()?->format(DATE_ATOM),
            trialEnd: $subscription->trialEnd()?->format(DATE_ATOM),
            pausedAt: $subscription->pausedAt()?->format(DATE_ATOM),
            cancelledAt: $subscription->cancelledAt()?->format(DATE_ATOM),
            cancelAtPeriodEnd: $subscription->cancelAtPeriodEnd(),
            paymentMethodId: $subscription->paymentMethodId(),
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
            'customerId' => $this->customerId,
            'subscriptionPlanId' => $this->subscriptionPlanId,
            'status' => $this->status,
            'currentPeriodStart' => $this->currentPeriodStart,
            'currentPeriodEnd' => $this->currentPeriodEnd,
            'trialStart' => $this->trialStart,
            'trialEnd' => $this->trialEnd,
            'pausedAt' => $this->pausedAt,
            'cancelledAt' => $this->cancelledAt,
            'cancelAtPeriodEnd' => $this->cancelAtPeriodEnd,
            'paymentMethodId' => $this->paymentMethodId,
        ];
    }
}
