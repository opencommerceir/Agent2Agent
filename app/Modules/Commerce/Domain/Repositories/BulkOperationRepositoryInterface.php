<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\BulkOperation;
use App\Modules\Commerce\Domain\Entities\BulkOperationItem;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationStatus;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;

interface BulkOperationRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?BulkOperation;

    /**
     * @return list<BulkOperation>
     */
    public function listByTenant(int $tenantId, ?BulkOperationType $type = null, ?BulkOperationStatus $status = null): array;

    public function save(BulkOperation $operation): BulkOperation;

    /**
     * Appends one `BulkOperationItem` row against an already-persisted
     * BulkOperation — deliberately a standalone append, not part of
     * `save()`'s own write, since items are created one at a time by a
     * long-running Job rather than as a fixed collection at construction
     * (see `BulkOperation`'s own docblock for why this repository
     * doesn't own a full `items()` collection the way
     * `WarehouseTransferRepositoryInterface` owns
     * `WarehouseTransferItem`).
     */
    public function saveItem(int $bulkOperationId, int $tenantId, BulkOperationItem $item): void;

    /**
     * @return list<BulkOperationItem>
     */
    public function listItems(int $bulkOperationId, int $tenantId, ?string $status = null): array;
}
