<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\BulkOperationData;
use App\Modules\Commerce\Application\Jobs\ProcessBulkUpdateJob;
use App\Modules\Commerce\Domain\Entities\BulkOperation;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;
use InvalidArgumentException;

/**
 * Kicks off a Bulk Status Update run. A bogus status name is a
 * whole-request problem, not a per-row one — validated here, before any
 * BulkOperation even exists, so we never create an Operation record only
 * to watch it fail every single row for the same reason. See
 * `BulkPriceUpdateAction`'s own docblock for the sync-driver re-fetch.
 */
final class BulkStatusUpdateAction
{
    public function __construct(
        private readonly BulkOperationRepositoryInterface $operations,
    ) {
    }

    /**
     * @param list<int> $productIds
     */
    public function execute(
        int $tenantId,
        int $createdBy,
        array $productIds,
        string $newStatus,
    ): BulkOperationData {
        try {
            ProductStatus::from($newStatus);
        } catch (\ValueError) {
            throw new InvalidArgumentException("Invalid product status [{$newStatus}].");
        }

        $operation = BulkOperation::create(
            tenantId: $tenantId,
            type: BulkOperationType::BulkStatusUpdate,
            createdBy: $createdBy,
            totalRows: count($productIds),
        );

        $operation = $this->operations->save($operation);

        ProcessBulkUpdateJob::dispatch(
            $operation->id(),
            $tenantId,
            'status',
            [
                'product_ids' => $productIds,
                'new_status' => $newStatus,
            ],
        );

        $final = $this->operations->findById($operation->id(), $tenantId) ?? $operation;

        return BulkOperationData::fromEntity($final);
    }
}
