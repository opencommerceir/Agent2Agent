<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\Payment;

final class PaymentData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $orderId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $method,
        public readonly string $status,
        public readonly ?string $transactionId,
        public readonly ?string $gateway = null,
    ) {
    }

    public static function fromEntity(Payment $payment): self
    {
        return new self(
            id: $payment->id(),
            tenantId: $payment->tenantId(),
            orderId: $payment->orderId(),
            amount: $payment->amount()->amount(),
            currency: $payment->amount()->currency(),
            method: $payment->method()->value,
            status: $payment->status()->value,
            transactionId: $payment->transactionId(),
            gateway: $payment->gateway(),
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
            'orderId' => $this->orderId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'status' => $this->status,
            'transactionId' => $this->transactionId,
            'gateway' => $this->gateway,
        ];
    }
}
