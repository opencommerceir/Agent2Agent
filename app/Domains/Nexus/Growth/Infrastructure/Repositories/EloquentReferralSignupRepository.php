<?php

namespace App\Domains\Nexus\Growth\Infrastructure\Repositories;

use App\Domains\Nexus\Growth\Domain\Entities\ReferralSignup as ReferralSignupEntity;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralSignupRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\ValueObjects\ReferralSignupStatus;
use App\Domains\Nexus\Growth\Infrastructure\Models\ReferralSignup as ReferralSignupModel;
use DateTimeImmutable;

class EloquentReferralSignupRepository implements ReferralSignupRepositoryInterface
{
    public function findByRefereeId(int $refereeBusinessId): ?ReferralSignupEntity
    {
        $model = ReferralSignupModel::query()->where('referee_business_id', $refereeBusinessId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByReferrerId(int $referrerBusinessId): array
    {
        return ReferralSignupModel::query()
            ->where('referrer_business_id', $referrerBusinessId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ReferralSignupModel $model) => $this->toEntity($model))
            ->all();
    }

    public function countByReferrerId(int $referrerBusinessId): int
    {
        return ReferralSignupModel::query()->where('referrer_business_id', $referrerBusinessId)->count();
    }

    public function save(ReferralSignupEntity $signup): ReferralSignupEntity
    {
        $model = $signup->id()
            ? ReferralSignupModel::query()->findOrFail($signup->id())
            : new ReferralSignupModel();

        $model->referrer_business_id = $signup->referrerBusinessId();
        $model->referee_business_id = $signup->refereeBusinessId();
        $model->referral_code = $signup->referralCode();
        $model->status = $signup->status()->value;
        $model->rewarded_at = $signup->rewardedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ReferralSignupModel $model): ReferralSignupEntity
    {
        return new ReferralSignupEntity(
            id: $model->id,
            referrerBusinessId: $model->referrer_business_id,
            refereeBusinessId: $model->referee_business_id,
            referralCode: $model->referral_code,
            status: ReferralSignupStatus::from($model->status),
            rewardedAt: $model->rewarded_at ? DateTimeImmutable::createFromInterface($model->rewarded_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
