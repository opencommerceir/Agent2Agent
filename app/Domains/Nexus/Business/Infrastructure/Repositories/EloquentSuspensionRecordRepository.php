<?php

namespace App\Domains\Nexus\Business\Infrastructure\Repositories;

use App\Domains\Nexus\Business\Domain\Entities\SuspensionRecord as SuspensionRecordEntity;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionRecordRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\SuspensionTrigger;
use App\Domains\Nexus\Business\Infrastructure\Models\SuspensionRecord as SuspensionRecordModel;
use DateTimeImmutable;

class EloquentSuspensionRecordRepository implements SuspensionRecordRepositoryInterface
{
    public function findByBusinessId(int $businessId): array
    {
        return SuspensionRecordModel::query()
            ->where('business_id', $businessId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (SuspensionRecordModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(SuspensionRecordEntity $record): SuspensionRecordEntity
    {
        $model = new SuspensionRecordModel();
        $model->business_id = $record->businessId();
        $model->action = $record->action()->value;
        $model->reason = $record->reason();
        $model->triggered_by = $record->triggeredBy()->value;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(SuspensionRecordModel $model): SuspensionRecordEntity
    {
        return SuspensionRecordEntity::record(
            businessId: $model->business_id,
            action: SuspensionAction::from($model->action),
            reason: $model->reason,
            triggeredBy: SuspensionTrigger::from($model->triggered_by),
        );
    }
}
