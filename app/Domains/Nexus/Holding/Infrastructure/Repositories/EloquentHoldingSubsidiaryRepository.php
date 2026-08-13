<?php

namespace App\Domains\Nexus\Holding\Infrastructure\Repositories;

use App\Domains\Nexus\Holding\Domain\Entities\HoldingSubsidiary as HoldingSubsidiaryEntity;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\ValueObjects\SubsidiaryStatus;
use App\Domains\Nexus\Holding\Infrastructure\Models\HoldingSubsidiary as HoldingSubsidiaryModel;
use DateTimeImmutable;

class EloquentHoldingSubsidiaryRepository implements HoldingSubsidiaryRepositoryInterface
{
    public function findById(int $id): ?HoldingSubsidiaryEntity
    {
        $model = HoldingSubsidiaryModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByHoldingId(int $holdingId): array
    {
        return HoldingSubsidiaryModel::query()
            ->where('holding_id', $holdingId)
            ->orderBy('invited_at')
            ->get()
            ->map(fn (HoldingSubsidiaryModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findByHoldingAndBusiness(int $holdingId, int $businessId): ?HoldingSubsidiaryEntity
    {
        $model = HoldingSubsidiaryModel::query()
            ->where('holding_id', $holdingId)
            ->where('business_id', $businessId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findActiveOrInvitedByBusinessId(int $businessId): ?HoldingSubsidiaryEntity
    {
        $model = HoldingSubsidiaryModel::query()
            ->where('business_id', $businessId)
            ->whereIn('status', [SubsidiaryStatus::Invited->value, SubsidiaryStatus::Active->value])
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findInvitationsForBusiness(int $businessId): array
    {
        return HoldingSubsidiaryModel::query()
            ->where('business_id', $businessId)
            ->where('status', SubsidiaryStatus::Invited->value)
            ->orderByDesc('invited_at')
            ->get()
            ->map(fn (HoldingSubsidiaryModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(HoldingSubsidiaryEntity $subsidiary): HoldingSubsidiaryEntity
    {
        $model = $subsidiary->id()
            ? HoldingSubsidiaryModel::query()->findOrFail($subsidiary->id())
            : new HoldingSubsidiaryModel();

        $model->holding_id = $subsidiary->holdingId();
        $model->business_id = $subsidiary->businessId();
        $model->status = $subsidiary->status()->value;
        $model->invited_at = $subsidiary->invitedAt();
        $model->responded_at = $subsidiary->respondedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(HoldingSubsidiaryModel $model): HoldingSubsidiaryEntity
    {
        return new HoldingSubsidiaryEntity(
            id: $model->id,
            holdingId: $model->holding_id,
            businessId: $model->business_id,
            status: SubsidiaryStatus::from($model->status),
            invitedAt: DateTimeImmutable::createFromInterface($model->invited_at),
            respondedAt: $model->responded_at ? DateTimeImmutable::createFromInterface($model->responded_at) : null,
        );
    }
}
