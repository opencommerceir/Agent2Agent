<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\BulkOperationData;
use App\Modules\Commerce\Application\Jobs\ProcessBulkExportJob;
use App\Modules\Commerce\Domain\Entities\BulkOperation;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Kicks off an Order CSV export. Unlike an import, there is no input file
 * to validate up front — the output file is `ProcessBulkExportJob`'s own
 * result, not this Action's input, so the BulkOperation is created with no
 * filePath at all (it doubles as the export's own output path once the Job
 * sets it — see that entity's own filePath() docblock note).
 *
 * $startDate/$endDate/$status are parsed here purely to fail fast — an
 * invalid date or status string is rejected synchronously, before a
 * BulkOperation row is ever created, exactly like ImportProductsAction's
 * own missing-file check. The parsed values themselves are then discarded:
 * `ProcessBulkExportJob`'s constructor only accepts primitives (a queued
 * Job's constructor args are serialized onto the queue — see this stage's
 * own HANDOFF note), so the original raw strings are what actually get
 * dispatched, and the Job re-parses them itself.
 *
 * Re-fetches the BulkOperation after dispatch (same reasoning as
 * BulkPriceUpdateAction) since under the `sync` queue driver the Job has
 * already produced the real export file and completed by the time
 * dispatch() returns — downloadUrl needs that real, final filePath.
 */
final class ExportOrdersAction
{
    public function __construct(
        private readonly BulkOperationRepositoryInterface $operations,
    ) {
    }

    /**
     * @return array{operation: BulkOperationData, downloadUrl: ?string}
     */
    public function execute(
        int $tenantId,
        int $createdBy,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $status = null,
    ): array {
        $this->assertValidDate($startDate);
        $this->assertValidDate($endDate);

        if ($status !== null) {
            OrderStatus::from($status); // throws ValueError for an unknown status string
        }

        $operation = BulkOperation::create(
            tenantId: $tenantId,
            type: BulkOperationType::ExportOrders,
            createdBy: $createdBy,
        );

        $operation = $this->operations->save($operation);

        ProcessBulkExportJob::dispatch($operation->id(), $tenantId, $startDate, $endDate, $status);

        $final = $this->operations->findById($operation->id(), $tenantId) ?? $operation;

        $downloadUrl = $final->filePath() !== null
            ? Storage::disk('public')->url($final->filePath())
            : null;

        return [
            'operation' => BulkOperationData::fromEntity($final),
            'downloadUrl' => $downloadUrl,
        ];
    }

    private function assertValidDate(?string $date): void
    {
        if ($date === null) {
            return;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false) {
            throw new InvalidArgumentException("Invalid date [{$date}]. Expected format Y-m-d.");
        }
    }
}
