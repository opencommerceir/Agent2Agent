<?php

namespace App\Domains\Nexus\Business\Infrastructure\Repositories;

use App\Domains\Nexus\Business\Domain\Entities\SuspensionAppeal as SuspensionAppealEntity;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionAppealRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionAppealStatus;
use App\Domains\Nexus\Business\Infrastructure\Models\SuspensionAppeal as SuspensionAppealModel;
use DateTimeImmutable;

class EloquentSuspensionAppealRepository implements SuspensionAppealRepositoryInterface
{
    public function findById(int $id): ?SuspensionAppealEntity
    {
        $model = SuspensionAppealModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByStatus(SuspensionAppealStatus $status): array
    {
        return SuspensionAppealModel::query()
            ->where('status', $status->value)
            ->orderByDesc('id')
            ->get()
            ->map(fn (SuspensionAppealModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(SuspensionAppealEntity $appeal): SuspensionAppealEntity
    {
        $model = $appeal->id()
            ? SuspensionAppealModel::query()->findOrFail($appeal->id())
            : new SuspensionAppealModel();

        $model->business_id = $appeal->businessId();
        $model->message = $appeal->message();
        $model->status = $appeal->status()->value;
        $model->resolved_at = $appeal->resolvedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(SuspensionAppealModel $model): SuspensionAppealEntity
    {
        return SuspensionAppealEntity::reconstruct(
            id: $model->id,
            businessId: $model->business_id,
            message: $model->message,
            status: SuspensionAppealStatus::from($model->status),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            resolvedAt: $model->resolved_at ? DateTimeImmutable::createFromInterface($model->resolved_at) : null,
        );
    }
}
