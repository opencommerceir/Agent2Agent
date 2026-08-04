<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice;

final class SubscriptionInvoiceData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $subscriptionId,
        public readonly ?int $orderId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly string $dueDate,
        public readonly ?string $paidAt,
        public readonly ?string $failedAt,
        public readonly int $retryCount,
    ) {
    }

    public static function fromEntity(SubscriptionInvoice $invoice): self
    {
        return new self(
            id: $invoice->id(),
            tenantId: $invoice->tenantId(),
            subscriptionId: $invoice->subscriptionId(),
            orderId: $invoice->orderId(),
            amount: $invoice->amount()->amount(),
            currency: $invoice->amount()->currency(),
            status: $invoice->status()->value,
            dueDate: $invoice->dueDate()->format(DATE_ATOM),
            paidAt: $invoice->paidAt()?->format(DATE_ATOM),
            failedAt: $invoice->failedAt()?->format(DATE_ATOM),
            retryCount: $invoice->retryCount(),
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
            'subscriptionId' => $this->subscriptionId,
            'orderId' => $this->orderId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'dueDate' => $this->dueDate,
            'paidAt' => $this->paidAt,
            'failedAt' => $this->failedAt,
            'retryCount' => $this->retryCount,
        ];
    }
}
