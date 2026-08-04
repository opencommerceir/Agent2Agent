<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionInvoiceStatus;
use DateTimeImmutable;

/**
 * One billing attempt for a Subscription's own current period. `orderId`
 * is nullable and, this stage, always null — SubscriptionInvoice charges
 * directly through PaymentGatewayInterface (the same port
 * ProcessPaymentAction already uses) rather than manufacturing a full
 * Cart -> Order -> Payment pipeline for something that isn't a Product
 * with Inventory (see ProcessSubscriptionRenewalAction's own docblock for
 * the full reasoning). No writer sets `orderId` this stage — a documented,
 * deliberate gap, the same shape `shipping_methods.rate_per_km` had no
 * writer for its own first stage (§7.22).
 *
 * Retry (rule §د.5: "۳ بار retry با فاصله ۳ روز, سپس status = past_due"):
 * `retryCount` increments on every failure, including the very first —
 * `hasExhaustedRetries()` is true once it reaches 3, at which point the
 * *Subscription* (not this invoice) transitions to PastDue.
 * `isRetryDue()` is the pure predicate `SubscriptionInvoiceRepositoryInterface::findDueForRetry()`
 * filters by — no separate `next_retry_at` column exists; the 3-day gap is
 * computed from `failedAt` + the interval each time, the same "derive it,
 * don't store a redundant column" reasoning `LoyaltyAccount`'s own
 * docblock already gives for not storing `total_points_expired`.
 */
final class SubscriptionInvoice
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $subscriptionId,
        private readonly ?int $orderId,
        private readonly Money $amount,
        private SubscriptionInvoiceStatus $status,
        private readonly DateTimeImmutable $dueDate,
        private ?DateTimeImmutable $paidAt,
        private ?DateTimeImmutable $failedAt,
        private int $retryCount,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(int $tenantId, int $subscriptionId, Money $amount, DateTimeImmutable $dueDate): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            subscriptionId: $subscriptionId,
            orderId: null,
            amount: $amount,
            status: SubscriptionInvoiceStatus::Pending,
            dueDate: $dueDate,
            paidAt: null,
            failedAt: null,
            retryCount: 0,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function markPaid(): void
    {
        $this->status = SubscriptionInvoiceStatus::Paid;
        $this->paidAt = new DateTimeImmutable();
    }

    public function markFailed(): void
    {
        $this->status = SubscriptionInvoiceStatus::Failed;
        $this->failedAt = new DateTimeImmutable();
        $this->retryCount++;
    }

    public function hasExhaustedRetries(): bool
    {
        return $this->retryCount >= self::MAX_RETRIES;
    }

    public function isRetryDue(DateTimeImmutable $now, int $intervalDays = 3): bool
    {
        return $this->status === SubscriptionInvoiceStatus::Failed
            && ! $this->hasExhaustedRetries()
            && $this->failedAt !== null
            && $this->failedAt->modify("+{$intervalDays} days") <= $now;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function subscriptionId(): int
    {
        return $this->subscriptionId;
    }

    public function orderId(): ?int
    {
        return $this->orderId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function status(): SubscriptionInvoiceStatus
    {
        return $this->status;
    }

    public function dueDate(): DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function paidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function failedAt(): ?DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function retryCount(): int
    {
        return $this->retryCount;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
