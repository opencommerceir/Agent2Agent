<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\BulkOperationData;
use App\Modules\Commerce\Application\Jobs\ProcessBulkImportJob;
use App\Modules\Commerce\Domain\Entities\BulkOperation;
use App\Modules\Commerce\Domain\Exceptions\InvalidCsvFormatException;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;
use Illuminate\Support\Facades\Storage;

/**
 * Kicks off a Product CSV import: verifies the file is really there
 * *before* a BulkOperation row ever exists for it (a missing file is a
 * synchronous, immediate rejection — never worth queuing a Job just to
 * fail it), creates the tracking BulkOperation (Pending — row counting and
 * all per-row work belongs to `ProcessBulkImportJob`, not this Action),
 * and hands off.
 *
 * $filePath is relative to the 'local' disk's own `bulk_operations/`
 * directory, not the disk root — the exact convention this stage's own
 * `create_bulk_operations_table` migration already documents on its
 * `file_path` column ("relative to the local disk's bulk_operations/
 * directory"). There is no file-upload endpoint anywhere in this codebase
 * (same migration docblock); an Agent is expected to have placed the file
 * there out of band and passes e.g. "imports/products-2026.csv", not
 * "bulk_operations/imports/products-2026.csv". Stored on the
 * BulkOperation exactly as given; `ProcessBulkImportJob` re-adds the
 * `bulk_operations/` prefix itself when resolving the absolute path.
 *
 * Required CSV columns: sku, name, price, currency, category, status,
 * stock_quantity — see `ProcessBulkImportJob`'s own row-processing
 * docblock for which of these are hard-required vs. enrichment-only.
 *
 * $options is accepted but unused this stage — reserved for a future
 * per-run override (e.g. a dry-run flag, a custom column mapping) without
 * another breaking signature change, the same "widen, don't duplicate"
 * shape every other cross-stage extension in this codebase already uses.
 *
 * Mirrors BulkPriceUpdateAction's own re-fetch-after-dispatch shape: under
 * the `sync` queue driver (this codebase's test connection) the Job has
 * already fully run by the time dispatch() returns, so the BulkOperation
 * is re-fetched to return its true final state rather than the
 * just-created Pending snapshot.
 */
final class ImportProductsAction
{
    public function __construct(
        private readonly BulkOperationRepositoryInterface $operations,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function execute(int $tenantId, int $createdBy, string $filePath, array $options = []): BulkOperationData
    {
        if (! Storage::disk('local')->exists("bulk_operations/{$filePath}")) {
            throw new InvalidCsvFormatException("CSV file [{$filePath}] was not found under the 'local' disk's bulk_operations/ directory.");
        }

        $operation = BulkOperation::create(
            tenantId: $tenantId,
            type: BulkOperationType::ImportProducts,
            createdBy: $createdBy,
            filePath: $filePath,
        );

        $operation = $this->operations->save($operation);

        ProcessBulkImportJob::dispatch($operation->id(), $tenantId);

        $final = $this->operations->findById($operation->id(), $tenantId) ?? $operation;

        return BulkOperationData::fromEntity($final);
    }
}
