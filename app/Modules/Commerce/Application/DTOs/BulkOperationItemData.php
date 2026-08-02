<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\BulkOperationItem;

final class BulkOperationItemData
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly array $data,
        public readonly string $status,
        public readonly ?string $errorMessage,
        public readonly ?int $entityId,
    ) {
    }

    public static function fromEntity(BulkOperationItem $item): self
    {
        return new self(
            rowNumber: $item->rowNumber(),
            data: $item->data(),
            status: $item->status(),
            errorMessage: $item->errorMessage(),
            entityId: $item->entityId(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rowNumber' => $this->rowNumber,
            'data' => $this->data,
            'status' => $this->status,
            'errorMessage' => $this->errorMessage,
            'entityId' => $this->entityId,
        ];
    }
}
