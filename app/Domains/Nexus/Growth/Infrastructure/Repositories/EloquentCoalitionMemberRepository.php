<?php

namespace App\Domains\Nexus\Growth\Infrastructure\Repositories;

use App\Domains\Nexus\Growth\Domain\Entities\CoalitionMember as CoalitionMemberEntity;
use App\Domains\Nexus\Growth\Domain\Repositories\CoalitionMemberRepositoryInterface;
use App\Domains\Nexus\Growth\Infrastructure\Models\CoalitionMember as CoalitionMemberModel;
use DateTimeImmutable;

class EloquentCoalitionMemberRepository implements CoalitionMemberRepositoryInterface
{
    public function findByCoalitionId(int $coalitionId): array
    {
        return CoalitionMemberModel::query()
            ->where('coalition_id', $coalitionId)
            ->orderBy('joined_at')
            ->get()
            ->map(fn (CoalitionMemberModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findByCoalitionAndBusiness(int $coalitionId, int $businessId): ?CoalitionMemberEntity
    {
        $model = CoalitionMemberModel::query()
            ->where('coalition_id', $coalitionId)
            ->where('business_id', $businessId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(CoalitionMemberEntity $member): CoalitionMemberEntity
    {
        $model = $member->id()
            ? CoalitionMemberModel::query()->findOrFail($member->id())
            : new CoalitionMemberModel();

        $model->coalition_id = $member->coalitionId();
        $model->business_id = $member->businessId();
        $model->quantity = $member->quantity();
        $model->joined_at = $member->joinedAt();
        $model->save();

        return $this->toEntity($model);
    }

    public function delete(int $id): void
    {
        CoalitionMemberModel::query()->where('id', $id)->delete();
    }

    private function toEntity(CoalitionMemberModel $model): CoalitionMemberEntity
    {
        return new CoalitionMemberEntity(
            id: $model->id,
            coalitionId: $model->coalition_id,
            businessId: $model->business_id,
            quantity: $model->quantity,
            joinedAt: DateTimeImmutable::createFromInterface($model->joined_at),
        );
    }
}
