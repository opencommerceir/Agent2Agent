<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\BulkOperation as BulkOperationEntity;
use App\Modules\Commerce\Domain\Entities\BulkOperationItem;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationStatus;
use App\Modules\Commerce\Domain\ValueObjects\BulkOperationType;
use App\Modules\Commerce\Infrastructure\Models\BulkOperation as BulkOperationModel;
use App\Modules\Commerce\Infrastructure\Models\BulkOperationItem as BulkOperationItemModel;
use DateTimeImmutable;

class EloquentBulkOperationRepository implements BulkOperationRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?BulkOperationEntity
    {
        $model = BulkOperationModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function listByTenant(int $tenantId, ?BulkOperationType $type = null, ?BulkOperationStatus $status = null): array
    {
        $builder = BulkOperationModel::query()->where('tenant_id', $tenantId);

        if ($type !== null) {
            $builder->where('type', $type->value);
        }

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        return $builder->orderBy('id', 'desc')
            ->get()
            ->map(fn (BulkOperationModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(BulkOperationEntity $operation): BulkOperationEntity
    {
        $model = $operation->id()
            ? BulkOperationModel::query()->where('tenant_id', $operation->tenantId())->findOrFail($operation->id())
            : new BulkOperationModel();

        $model->tenant_id = $operation->tenantId();
        $model->type = $operation->type()->value;
        $model->status = $operation->status()->value;
        $model->total_rows = $operation->totalRows();
        $model->processed_rows = $operation->processedRows();
        $model->success_rows = $operation->successRows();
        $model->failed_rows = $operation->failedRows();
        $model->file_path = $operation->filePath();
        $model->error_file_path = $operation->errorFilePath();
        $model->started_at = $operation->startedAt();
        $model->completed_at = $operation->completedAt();
        $model->created_by = $operation->createdBy();
        $model->save();

        return $this->toEntity($model);
    }

    public function saveItem(int $bulkOperationId, int $tenantId, BulkOperationItem $item): void
    {
        // tenant_id is enforced here, not on bulk_operation_items itself
        // (which has none of its own, inherited through bulk_operation_id)
        // — findOrFail guarantees this BulkOperation genuinely belongs to
        // the caller's own tenant before any item is ever attached to it.
        $operation = BulkOperationModel::query()->where('tenant_id', $tenantId)->findOrFail($bulkOperationId);

        $operation->items()->create([
            'row_number' => $item->rowNumber(),
            'data' => $item->data(),
            'status' => $item->status(),
            'error_message' => $item->errorMessage(),
            'entity_id' => $item->entityId(),
            'processed_at' => $item->processedAt(),
        ]);
    }

    public function listItems(int $bulkOperationId, int $tenantId, ?string $status = null): array
    {
        $operation = BulkOperationModel::query()->where('tenant_id', $tenantId)->findOrFail($bulkOperationId);

        $builder = $operation->items();

        if ($status !== null) {
            $builder->where('status', $status);
        }

        return $builder->orderBy('row_number')
            ->get()
            ->map(fn (BulkOperationItemModel $model) => new BulkOperationItem(
                rowNumber: $model->row_number,
                data: $model->data,
                status: $model->status,
                errorMessage: $model->error_message,
                entityId: $model->entity_id,
                processedAt: DateTimeImmutable::createFromInterface($model->processed_at),
            ))
            ->all();
    }

    private function toEntity(BulkOperationModel $model): BulkOperationEntity
    {
        return new BulkOperationEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            type: BulkOperationType::from($model->type),
            status: BulkOperationStatus::from($model->status),
            totalRows: $model->total_rows,
            processedRows: $model->processed_rows,
            successRows: $model->success_rows,
            failedRows: $model->failed_rows,
            filePath: $model->file_path,
            errorFilePath: $model->error_file_path,
            startedAt: $model->started_at ? DateTimeImmutable::createFromInterface($model->started_at) : null,
            completedAt: $model->completed_at ? DateTimeImmutable::createFromInterface($model->completed_at) : null,
            createdBy: $model->created_by,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            updatedAt: DateTimeImmutable::createFromInterface($model->updated_at),
        );
    }
}
