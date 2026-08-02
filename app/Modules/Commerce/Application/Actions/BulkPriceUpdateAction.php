<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\BulkOperationData;
use App\Modules\Commerce\Application\Jobs\ProcessBulkUpdateJob;
use App\Modules\Commerce\Domain\Entities\BulkOperation;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;

/**
 * Kicks off a Bulk Price Update run: creates the tracking BulkOperation
 * (Pending, totalRows known up front — unlike a CSV import, the id count
 * is already in hand) and hands the real work to `ProcessBulkUpdateJob`.
 * Under the `sync` queue driver (this codebase's test connection) the Job
 * has already fully run by the time `dispatch()` returns below, so the
 * BulkOperation is re-fetched afterward to return its true final state
 * rather than the just-created Pending snapshot.
 */
final class BulkPriceUpdateAction
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
        int $newPriceAmount,
        string $newPriceCurrency,
    ): BulkOperationData {
        $operation = BulkOperation::create(
            tenantId: $tenantId,
            type: BulkOperationType::BulkPriceUpdate,
            createdBy: $createdBy,
            totalRows: count($productIds),
        );

        $operation = $this->operations->save($operation);

        ProcessBulkUpdateJob::dispatch(
            $operation->id(),
            $tenantId,
            'price',
            [
                'product_ids' => $productIds,
                'price_amount' => $newPriceAmount,
                'price_currency' => $newPriceCurrency,
            ],
        );

        $final = $this->operations->findById($operation->id(), $tenantId) ?? $operation;

        return BulkOperationData::fromEntity($final);
    }
}
