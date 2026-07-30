<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use App\Modules\Commerce\Domain\ValueObjects\PaymentStatus;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A record of a charge attempt against a specific Order. Only ever
 * created *after* the Payment Gateway has already responded — a failed
 * charge never produces a Payment row (ProcessPaymentAction's docblock),
 * since order_id is not nullable and no Order exists yet when a charge
 * is attempted (Payment-before-Order flow, per this stage's explicit
 * sequencing).
 */
final class Payment
{
    /**
     * @param array<string, mixed> $gatewayResponse
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $orderId,
        private readonly Money $amount,
        private readonly PaymentMethod $method,
        private PaymentStatus $status,
        private readonly ?string $transactionId,
        private readonly array $gatewayResponse,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $gatewayResponse
     */
    public static function record(
        int $tenantId,
        int $orderId,
        Money $amount,
        PaymentMethod $method,
        PaymentStatus $status,
        ?string $transactionId,
        array $gatewayResponse,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            orderId: $orderId,
            amount: $amount,
            method: $method,
            status: $status,
            transactionId: $transactionId,
            gatewayResponse: $gatewayResponse,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function refund(): void
    {
        if ($this->status !== PaymentStatus::Completed) {
            throw new InvalidArgumentException(
                "Only a completed payment can be refunded (current status: [{$this->status->value}])."
            );
        }

        $this->status = PaymentStatus::Refunded;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function orderId(): int
    {
        return $this->orderId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function transactionId(): ?string
    {
        return $this->transactionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function gatewayResponse(): array
    {
        return $this->gatewayResponse;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::Completed;
    }
}
