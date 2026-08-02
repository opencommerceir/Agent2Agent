<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\BulkOperation;

final class BulkOperationData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $type,
        public readonly string $status,
        public readonly int $totalRows,
        public readonly int $processedRows,
        public readonly int $successRows,
        public readonly int $failedRows,
        public readonly ?string $filePath,
        public readonly ?string $errorFilePath,
        public readonly ?string $startedAt,
        public readonly ?string $completedAt,
        public readonly int $createdBy,
    ) {
    }

    public static function fromEntity(BulkOperation $operation): self
    {
        return new self(
            id: $operation->id(),
            tenantId: $operation->tenantId(),
            type: $operation->type()->value,
            status: $operation->status()->value,
            totalRows: $operation->totalRows(),
            processedRows: $operation->processedRows(),
            successRows: $operation->successRows(),
            failedRows: $operation->failedRows(),
            filePath: $operation->filePath(),
            errorFilePath: $operation->errorFilePath(),
            startedAt: $operation->startedAt()?->format(DATE_ATOM),
            completedAt: $operation->completedAt()?->format(DATE_ATOM),
            createdBy: $operation->createdBy(),
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
            'type' => $this->type,
            'status' => $this->status,
            'totalRows' => $this->totalRows,
            'processedRows' => $this->processedRows,
            'successRows' => $this->successRows,
            'failedRows' => $this->failedRows,
            'filePath' => $this->filePath,
            'errorFilePath' => $this->errorFilePath,
            'startedAt' => $this->startedAt,
            'completedAt' => $this->completedAt,
            'createdBy' => $this->createdBy,
        ];
    }
}
