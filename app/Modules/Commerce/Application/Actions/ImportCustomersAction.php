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
 * Kicks off a Customer CSV import — same shape as ImportProductsAction
 * (see that Action's own docblock for the $filePath convention — relative
 * to the 'local' disk's `bulk_operations/` directory, not the disk root —
 * the re-fetch-after-dispatch reasoning, and $options), differing only in
 * BulkOperationType and the CSV's own required columns: email,
 * first_name, last_name, phone (phone is enrichment-only — see
 * `ProcessBulkImportJob`'s own row-processing docblock).
 */
final class ImportCustomersAction
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
            type: BulkOperationType::ImportCustomers,
            createdBy: $createdBy,
            filePath: $filePath,
        );

        $operation = $this->operations->save($operation);

        ProcessBulkImportJob::dispatch($operation->id(), $tenantId);

        $final = $this->operations->findById($operation->id(), $tenantId) ?? $operation;

        return BulkOperationData::fromEntity($final);
    }
}
