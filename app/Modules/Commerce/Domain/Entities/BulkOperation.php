<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\BulkOperationStatus;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Tracks one bulk import/export/update run end to end — the aggregate a
 * queued Job (`ProcessBulkImportJob`/`ProcessBulkExportJob`/
 * `ProcessBulkUpdateJob`) reports progress against as it works through a
 * CSV file or an array of ids. Deliberately does NOT hold its own
 * `BulkOperationItem[]` collection the way `WarehouseTransfer` holds its
 * frozen `WarehouseTransferItem[]` (§7.22) — a Transfer's items are a
 * handful, fixed at creation; a BulkOperation's items are potentially
 * thousands, appended one at a time as a long-running Job works through
 * them. Re-saving a growing in-memory collection on every progress tick
 * would be exactly the anti-pattern rule §e ("از Eloquent aggregates
 * استفاده کن, نه loop در PHP") already warns against elsewhere in this
 * codebase. `BulkOperationRepositoryInterface::saveItem()` appends one row
 * at a time instead — see that interface's own docblock.
 *
 * State machine: Pending -> Processing -> {Completed, Partial, Failed}, or
 * Pending -> Failed directly (an unrecoverable, whole-file problem before
 * any row is ever attempted — a missing/unreadable file, for example).
 * Mirrors `Shipment`/`WarehouseTransfer`'s own `ALLOWED_TRANSITIONS` shape
 * exactly.
 */
final class BulkOperation
{
    /**
     * @var array<string, list<BulkOperationStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => [BulkOperationStatus::Processing, BulkOperationStatus::Failed],
        'processing' => [BulkOperationStatus::Completed, BulkOperationStatus::Partial, BulkOperationStatus::Failed],
        'completed' => [],
        'partial' => [],
        'failed' => [],
    ];

    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly BulkOperationType $type,
        private BulkOperationStatus $status,
        private int $totalRows,
        private int $processedRows,
        private int $successRows,
        private int $failedRows,
        private ?string $filePath,
        private ?string $errorFilePath,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $completedAt,
        private readonly int $createdBy,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        int $tenantId,
        BulkOperationType $type,
        int $createdBy,
        ?string $filePath = null,
        int $totalRows = 0,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: null,
            tenantId: $tenantId,
            type: $type,
            status: BulkOperationStatus::Pending,
            totalRows: $totalRows,
            processedRows: 0,
            successRows: 0,
            failedRows: 0,
            filePath: $filePath,
            errorFilePath: null,
            startedAt: null,
            completedAt: null,
            createdBy: $createdBy,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function start(int $totalRows): void
    {
        $this->transitionTo(BulkOperationStatus::Processing);
        $this->totalRows = $totalRows;
        $this->startedAt = new DateTimeImmutable();
    }

    /**
     * Called by the owning Job after each processed batch — real-time
     * progress tracking (rule §د.5), not a one-shot final tally. Only
     * legal while Processing (or, harmlessly, called once more right
     * before `complete()`/`fail()` themselves transition away from it).
     */
    public function recordProgress(int $processedRows, int $successRows, int $failedRows): void
    {
        $this->processedRows = $processedRows;
        $this->successRows = $successRows;
        $this->failedRows = $failedRows;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * The counterpart to `setErrorFilePath()` — an Export operation's own
     * output file (`ProcessBulkExportJob`'s own docblock). `filePath`
     * doubles as "input CSV" for an Import and "output CSV" for an Export;
     * there's no separate field for each, since one BulkOperation is never
     * both at once.
     */
    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function setErrorFilePath(string $errorFilePath): void
    {
        $this->errorFilePath = $errorFilePath;
    }

    /**
     * Completes the run, choosing the terminal status from the final row
     * counts rather than requiring the caller to name one: every row
     * succeeded -> Completed; some succeeded and some failed -> Partial
     * (rule §e.4's own status list); none succeeded at all (and at least
     * one row failed) -> Failed. A zero-row operation (e.g. an empty CSV)
     * completes as Completed, not Failed — there was nothing to fail.
     */
    public function complete(): void
    {
        $status = match (true) {
            $this->failedRows === 0 => BulkOperationStatus::Completed,
            $this->successRows > 0 => BulkOperationStatus::Partial,
            default => BulkOperationStatus::Failed,
        };

        $this->transitionTo($status);
        $this->completedAt = new DateTimeImmutable();
    }

    /**
     * An unrecoverable, whole-operation failure — a missing/unreadable
     * file, or any error that happens before a single row could even be
     * attempted. Legal from Pending (never got started) or Processing (a
     * fatal error mid-run, distinct from an ordinary per-row failure
     * `recordProgress()`'s own `failedRows` count already tracks).
     */
    public function fail(): void
    {
        $this->transitionTo(BulkOperationStatus::Failed);
        $this->completedAt = new DateTimeImmutable();
    }

    private function transitionTo(BulkOperationStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                "BulkOperation cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
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

    public function type(): BulkOperationType
    {
        return $this->type;
    }

    public function status(): BulkOperationStatus
    {
        return $this->status;
    }

    public function totalRows(): int
    {
        return $this->totalRows;
    }

    public function processedRows(): int
    {
        return $this->processedRows;
    }

    public function successRows(): int
    {
        return $this->successRows;
    }

    public function failedRows(): int
    {
        return $this->failedRows;
    }

    public function filePath(): ?string
    {
        return $this->filePath;
    }

    public function errorFilePath(): ?string
    {
        return $this->errorFilePath;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function createdBy(): int
    {
        return $this->createdBy;
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
