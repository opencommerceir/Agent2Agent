<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\WarehouseTransfer;

final class WarehouseTransferData
{
    /**
     * @param list<WarehouseTransferItemData> $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $sourceWarehouseId,
        public readonly int $destinationWarehouseId,
        public readonly string $status,
        public readonly int $requestedBy,
        public readonly ?int $approvedBy,
        public readonly ?string $completedAt,
        public readonly ?string $notes,
        public readonly array $items,
    ) {
    }

    public static function fromEntity(WarehouseTransfer $transfer): self
    {
        return new self(
            id: $transfer->id(),
            tenantId: $transfer->tenantId(),
            sourceWarehouseId: $transfer->sourceWarehouseId(),
            destinationWarehouseId: $transfer->destinationWarehouseId(),
            status: $transfer->status()->value,
            requestedBy: $transfer->requestedBy(),
            approvedBy: $transfer->approvedBy(),
            completedAt: $transfer->completedAt()?->format(DATE_ATOM),
            notes: $transfer->notes(),
            items: array_map(
                fn ($item) => WarehouseTransferItemData::fromEntity($item),
                $transfer->items(),
            ),
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
            'sourceWarehouseId' => $this->sourceWarehouseId,
            'destinationWarehouseId' => $this->destinationWarehouseId,
            'status' => $this->status,
            'requestedBy' => $this->requestedBy,
            'approvedBy' => $this->approvedBy,
            'completedAt' => $this->completedAt,
            'notes' => $this->notes,
            'items' => array_map(fn (WarehouseTransferItemData $item) => $item->toArray(), $this->items),
        ];
    }
}
