<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentSessionStatus;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * Bridges "we asked a redirect-based gateway (Zibal/Stripe/...) to start
 * a charge" and "the gateway confirmed it" — `Payment`/`Order` still
 * cannot exist until a charge is actually confirmed (`Payment.orderId`
 * stays non-nullable, `Payment`'s own existing invariant, untouched by
 * this addition), so this is where that in-between state actually lives.
 *
 * `total`/`tax`/`discount` are the pricing **frozen** at `initiate()`
 * time (computed once via `CalculatePricingAction`, never recomputed at
 * confirm time) — the same "compute once, apply durably later" principle
 * `Order.tax`/`discount`/`total` already establish for the synchronous
 * checkout path. `total` is what's actually sent to the gateway as the
 * amount to charge; `tax`/`discount` exist here only so
 * `ConfirmRedirectPaymentAction` can hand all three back to
 * `PlaceOrderAction` unchanged, without recomputing pricing a second
 * time against a Cart that may have since changed.
 *
 * `id` starts `null` and is assigned exactly once by the repository
 * (`assignId()`, mirrors `ExecutionPattern`'s own one-time-mutator
 * shape) — a real id must exist *before* `initiate()` is called, since
 * this session's own id is what gets sent to the gateway as the
 * `orderId`/`client_reference_id` a callback later looks it up by.
 * `providerReference` (the gateway's own trackId/session id) is
 * similarly assigned exactly once, by `markInitiated()`, once the
 * gateway has actually responded.
 */
final class PaymentSession
{
    /**
     * @var array<string, list<PaymentSessionStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => [PaymentSessionStatus::Completed, PaymentSessionStatus::Failed, PaymentSessionStatus::Cancelled],
    ];

    private function __construct(
        private ?int $id,
        private readonly int $tenantId,
        private readonly int $cartId,
        private readonly int $agentId,
        private readonly string $gateway,
        private ?string $providerReference,
        private readonly Money $total,
        private readonly Money $tax,
        private readonly Money $discount,
        private PaymentSessionStatus $status,
        private readonly ?string $couponCode,
        private readonly ?int $customerId,
        private readonly ?string $notes,
        private readonly ?string $region,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $completedAt,
        private ?int $orderId = null,
    ) {
    }

    public static function create(
        int $tenantId,
        int $cartId,
        int $agentId,
        string $gateway,
        Money $total,
        Money $tax,
        Money $discount,
        ?string $couponCode = null,
        ?int $customerId = null,
        ?string $notes = null,
        ?string $region = null,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            cartId: $cartId,
            agentId: $agentId,
            gateway: $gateway,
            providerReference: null,
            total: $total,
            tax: $tax,
            discount: $discount,
            status: PaymentSessionStatus::Pending,
            couponCode: $couponCode,
            customerId: $customerId,
            notes: $notes,
            region: $region,
            createdAt: new DateTimeImmutable(),
            completedAt: null,
        );
    }

    public static function reconstruct(
        int $id,
        int $tenantId,
        int $cartId,
        int $agentId,
        string $gateway,
        ?string $providerReference,
        Money $total,
        Money $tax,
        Money $discount,
        PaymentSessionStatus $status,
        ?string $couponCode,
        ?int $customerId,
        ?string $notes,
        ?string $region,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $completedAt,
        ?int $orderId = null,
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            cartId: $cartId,
            agentId: $agentId,
            gateway: $gateway,
            providerReference: $providerReference,
            total: $total,
            tax: $tax,
            discount: $discount,
            status: $status,
            couponCode: $couponCode,
            customerId: $customerId,
            notes: $notes,
            region: $region,
            createdAt: $createdAt,
            completedAt: $completedAt,
            orderId: $orderId,
        );
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new LogicException("PaymentSession already has id [{$this->id}]; assignId() is one-time only.");
        }

        $this->id = $id;
    }

    public function markInitiated(string $providerReference): void
    {
        if ($this->providerReference !== null) {
            throw new LogicException(
                "PaymentSession [{$this->id}] already has a providerReference; markInitiated() is one-time only."
            );
        }

        $this->providerReference = $providerReference;
    }

    public function complete(int $orderId): void
    {
        $this->transitionTo(PaymentSessionStatus::Completed);
        $this->completedAt = new DateTimeImmutable();
        $this->orderId = $orderId;
    }

    public function fail(): void
    {
        $this->transitionTo(PaymentSessionStatus::Failed);
        $this->completedAt = new DateTimeImmutable();
    }

    public function cancel(): void
    {
        $this->transitionTo(PaymentSessionStatus::Cancelled);
        $this->completedAt = new DateTimeImmutable();
    }

    private function transitionTo(PaymentSessionStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot transition PaymentSession from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function cartId(): int
    {
        return $this->cartId;
    }

    public function agentId(): int
    {
        return $this->agentId;
    }

    public function gateway(): string
    {
        return $this->gateway;
    }

    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    public function total(): Money
    {
        return $this->total;
    }

    public function tax(): Money
    {
        return $this->tax;
    }

    public function discount(): Money
    {
        return $this->discount;
    }

    public function status(): PaymentSessionStatus
    {
        return $this->status;
    }

    public function couponCode(): ?string
    {
        return $this->couponCode;
    }

    public function customerId(): ?int
    {
        return $this->customerId;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function region(): ?string
    {
        return $this->region;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function orderId(): ?int
    {
        return $this->orderId;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentSessionStatus::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentSessionStatus::Completed;
    }
}
