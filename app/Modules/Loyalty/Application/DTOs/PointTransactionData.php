<?php

namespace App\Modules\Loyalty\Application\DTOs;

use App\Modules\Loyalty\Domain\Entities\PointTransaction;

final class PointTransactionData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $loyaltyAccountId,
        public readonly int $points,
        public readonly string $transactionType,
        public readonly ?string $description,
        public readonly ?int $referenceId,
        public readonly ?string $expiresAt,
    ) {
    }

    public static function fromEntity(PointTransaction $transaction): self
    {
        return new self(
            id: $transaction->id(),
            tenantId: $transaction->tenantId(),
            loyaltyAccountId: $transaction->loyaltyAccountId(),
            points: $transaction->points(),
            transactionType: $transaction->transactionType()->value,
            description: $transaction->description(),
            referenceId: $transaction->referenceId(),
            expiresAt: $transaction->expiresAt()?->format(DATE_ATOM),
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
            'loyaltyAccountId' => $this->loyaltyAccountId,
            'points' => $this->points,
            'transactionType' => $this->transactionType,
            'description' => $this->description,
            'referenceId' => $this->referenceId,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
