<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\BulkOperationData;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationStatus;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;

final class ListBulkOperationsAction
{
    public function __construct(
        private readonly BulkOperationRepositoryInterface $operations,
    ) {
    }

    /**
     * @return list<BulkOperationData>
     */
    public function execute(int $tenantId, ?string $type = null, ?string $status = null): array
    {
        return array_map(
            fn ($operation) => BulkOperationData::fromEntity($operation),
            $this->operations->listByTenant(
                $tenantId,
                $type !== null ? BulkOperationType::from($type) : null,
                $status !== null ? BulkOperationStatus::from($status) : null,
            ),
        );
    }
}
