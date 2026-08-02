<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\TransferStatus;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A request to move stock of one or more Products/ProductVariants from
 * one Warehouse to another within the same tenant. `items` is frozen at
 * creation — the same Immutable Order Items shape Order/Invoice/Workflow
 * already establish (no "add an item to an existing transfer" operation;
 * requesting a different quantity means requesting a new transfer).
 *
 * State machine (rule §e.3: "Request -> Approve -> Reserve from source ->
 * In Transit -> Complete -> Add to destination"): mirrors Shipment's own
 * ALLOWED_TRANSITIONS map exactly (§7.12). `InTransit` is a legal
 * mid-flow state but unreached by any Action this stage — see
 * TransferStatus's own docblock. `approve()` also stamps `approvedBy`;
 * `complete()` stamps `completedAt` the first (and only) time the
 * Transfer ever reaches Completed. Inventory side effects (reserving at
 * the source, committing at the source, receiving at the destination)
 * are deliberately NOT this entity's responsibility — they live in
 * Approve/CompleteWarehouseTransferAction, the same "entity owns state,
 * Action owns cross-aggregate orchestration" split PlaceOrderAction has
 * relative to Order/Inventory.
 */
final class WarehouseTransfer
{
    /**
     * @var array<string, list<TransferStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => [TransferStatus::Approved, TransferStatus::Cancelled],
        'approved' => [TransferStatus::InTransit, TransferStatus::Completed, TransferStatus::Cancelled],
        'in_transit' => [TransferStatus::Completed, TransferStatus::Cancelled],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * @param list<WarehouseTransferItem> $items
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $sourceWarehouseId,
        private readonly int $destinationWarehouseId,
        private TransferStatus $status,
        private readonly int $requestedBy,
        private ?int $approvedBy,
        private ?DateTimeImmutable $completedAt,
        private readonly ?string $notes,
        private readonly array $items,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if ($sourceWarehouseId === $destinationWarehouseId) {
            throw new InvalidArgumentException('A WarehouseTransfer requires two different Warehouses.');
        }

        if ($items === []) {
            throw new InvalidArgumentException('A WarehouseTransfer requires at least one item.');
        }
    }

    /**
     * @param list<WarehouseTransferItem> $items
     */
    public static function request(
        int $tenantId,
        int $sourceWarehouseId,
        int $destinationWarehouseId,
        int $requestedBy,
        array $items,
        ?string $notes = null,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            tenantId: $tenantId,
            sourceWarehouseId: $sourceWarehouseId,
            destinationWarehouseId: $destinationWarehouseId,
            status: TransferStatus::Pending,
            requestedBy: $requestedBy,
            approvedBy: null,
            completedAt: null,
            notes: $notes,
            items: $items,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function approve(int $approvedBy): void
    {
        $this->transitionTo(TransferStatus::Approved);
        $this->approvedBy = $approvedBy;
    }

    public function complete(): void
    {
        $this->transitionTo(TransferStatus::Completed);
        $this->completedAt = new DateTimeImmutable();
    }

    public function cancel(): void
    {
        $this->transitionTo(TransferStatus::Cancelled);
    }

    private function transitionTo(TransferStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                "WarehouseTransfer cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function sourceWarehouseId(): int
    {
        return $this->sourceWarehouseId;
    }

    public function destinationWarehouseId(): int
    {
        return $this->destinationWarehouseId;
    }

    public function status(): TransferStatus
    {
        return $this->status;
    }

    public function requestedBy(): int
    {
        return $this->requestedBy;
    }

    public function approvedBy(): ?int
    {
        return $this->approvedBy;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    /**
     * @return list<WarehouseTransferItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
