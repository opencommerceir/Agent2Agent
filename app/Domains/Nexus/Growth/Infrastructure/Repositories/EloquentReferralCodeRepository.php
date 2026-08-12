<?php

namespace App\Domains\Nexus\Growth\Infrastructure\Repositories;

use App\Domains\Nexus\Growth\Domain\Entities\ReferralCode as ReferralCodeEntity;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use App\Domains\Nexus\Growth\Infrastructure\Models\ReferralCode as ReferralCodeModel;
use DateTimeImmutable;

class EloquentReferralCodeRepository implements ReferralCodeRepositoryInterface
{
    public function findByBusinessId(int $businessId): ?ReferralCodeEntity
    {
        $model = ReferralCodeModel::query()->where('business_id', $businessId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByCode(string $code): ?ReferralCodeEntity
    {
        $model = ReferralCodeModel::query()->where('code', $code)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function codeExists(string $code): bool
    {
        return ReferralCodeModel::query()->where('code', $code)->exists();
    }

    public function save(ReferralCodeEntity $code): ReferralCodeEntity
    {
        $model = $code->id()
            ? ReferralCodeModel::query()->findOrFail($code->id())
            : new ReferralCodeModel();

        $model->business_id = $code->businessId();
        $model->code = $code->code();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ReferralCodeModel $model): ReferralCodeEntity
    {
        return new ReferralCodeEntity(
            id: $model->id,
            businessId: $model->business_id,
            code: $model->code,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
