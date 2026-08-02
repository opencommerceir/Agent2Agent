<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\BulkOperationData;
use App\Modules\Commerce\Application\Jobs\ProcessBulkUpdateJob;
use App\Modules\Commerce\Domain\Entities\BulkOperation;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;

/**
 * Kicks off a Bulk Inventory Update run — sets on-hand stock to an exact
 * requested quantity per product/variant (`Inventory::setQuantityOnHand()`,
 * a direct administrative override, not a relative reserve/commit). See
 * `BulkPriceUpdateAction`'s own docblock for the sync-driver re-fetch.
 */
final class BulkInventoryUpdateAction
{
    public function __construct(
        private readonly BulkOperationRepositoryInterface $operations,
    ) {
    }

    /**
     * @param list<array{product_id: int, variant_id: ?int, quantity: int}> $updates
     */
    public function execute(
        int $tenantId,
        int $createdBy,
        array $updates,
    ): BulkOperationData {
        $operation = BulkOperation::create(
            tenantId: $tenantId,
            type: BulkOperationType::BulkInventoryUpdate,
            createdBy: $createdBy,
            totalRows: count($updates),
        );

        $operation = $this->operations->save($operation);

        ProcessBulkUpdateJob::dispatch(
            $operation->id(),
            $tenantId,
            'inventory',
            [
                'updates' => $updates,
            ],
        );

        $final = $this->operations->findById($operation->id(), $tenantId) ?? $operation;

        return BulkOperationData::fromEntity($final);
    }
}
