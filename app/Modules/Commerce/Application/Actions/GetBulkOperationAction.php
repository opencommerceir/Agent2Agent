<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\BulkOperationData;
use App\Modules\Commerce\Domain\Exceptions\BulkOperationNotFoundException;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;

final class GetBulkOperationAction
{
    public function __construct(
        private readonly BulkOperationRepositoryInterface $operations,
    ) {
    }

    public function execute(int $id, int $tenantId): BulkOperationData
    {
        $operation = $this->operations->findById($id, $tenantId);

        if (! $operation) {
            throw new BulkOperationNotFoundException("BulkOperation [{$id}] does not exist.");
        }

        return BulkOperationData::fromEntity($operation);
    }
}
