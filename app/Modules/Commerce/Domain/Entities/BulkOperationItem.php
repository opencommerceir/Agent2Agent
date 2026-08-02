<?php

namespace App\Modules\Commerce\Domain\Entities;

use DateTimeImmutable;

/**
 * One processed row of a BulkOperation — appended independently via
 * `BulkOperationRepositoryInterface::saveItem()` as a Job works through a
 * file/array, never held in memory as part of `BulkOperation`'s own
 * aggregate (see that entity's own docblock for why). No `id`/
 * `bulkOperationId` property, the same HANDOFF gotcha #10 shape every
 * other child-of-a-parent entity in this codebase has — the id is
 * supplied by the repository at persistence time, not carried on the
 * Domain object itself.
 */
final class BulkOperationItem
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly int $rowNumber,
        private readonly array $data,
        private readonly string $status,
        private readonly ?string $errorMessage,
        private readonly ?int $entityId,
        private readonly DateTimeImmutable $processedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function success(int $rowNumber, array $data, int $entityId): self
    {
        return new self($rowNumber, $data, 'success', null, $entityId, new DateTimeImmutable());
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function failed(int $rowNumber, array $data, string $errorMessage): self
    {
        return new self($rowNumber, $data, 'failed', $errorMessage, null, new DateTimeImmutable());
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function skipped(int $rowNumber, array $data, string $reason): self
    {
        return new self($rowNumber, $data, 'skipped', $reason, null, new DateTimeImmutable());
    }

    public function rowNumber(): int
    {
        return $this->rowNumber;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function entityId(): ?int
    {
        return $this->entityId;
    }

    public function processedAt(): DateTimeImmutable
    {
        return $this->processedAt;
    }
}
