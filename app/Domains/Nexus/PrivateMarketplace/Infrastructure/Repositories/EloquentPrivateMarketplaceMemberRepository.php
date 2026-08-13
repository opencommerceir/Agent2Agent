<?php

namespace App\Domains\Nexus\PrivateMarketplace\Infrastructure\Repositories;

use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplaceMember as PrivateMarketplaceMemberEntity;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceMemberRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\ValueObjects\PrivateMarketplaceMemberStatus;
use App\Domains\Nexus\PrivateMarketplace\Infrastructure\Models\PrivateMarketplaceMember as PrivateMarketplaceMemberModel;
use DateTimeImmutable;

class EloquentPrivateMarketplaceMemberRepository implements PrivateMarketplaceMemberRepositoryInterface
{
    public function findById(int $id): ?PrivateMarketplaceMemberEntity
    {
        $model = PrivateMarketplaceMemberModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByPrivateMarketplaceId(int $privateMarketplaceId): array
    {
        return PrivateMarketplaceMemberModel::query()
            ->where('private_marketplace_id', $privateMarketplaceId)
            ->orderBy('invited_at')
            ->get()
            ->map(fn (PrivateMarketplaceMemberModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findByMarketplaceAndBusiness(int $privateMarketplaceId, int $businessId): ?PrivateMarketplaceMemberEntity
    {
        $model = PrivateMarketplaceMemberModel::query()
            ->where('private_marketplace_id', $privateMarketplaceId)
            ->where('business_id', $businessId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findInvitationsForBusiness(int $businessId): array
    {
        return PrivateMarketplaceMemberModel::query()
            ->where('business_id', $businessId)
            ->where('status', PrivateMarketplaceMemberStatus::Invited->value)
            ->orderByDesc('invited_at')
            ->get()
            ->map(fn (PrivateMarketplaceMemberModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findActiveMembershipsForBusiness(int $businessId): array
    {
        return PrivateMarketplaceMemberModel::query()
            ->where('business_id', $businessId)
            ->where('status', PrivateMarketplaceMemberStatus::Active->value)
            ->get()
            ->map(fn (PrivateMarketplaceMemberModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(PrivateMarketplaceMemberEntity $member): PrivateMarketplaceMemberEntity
    {
        $model = $member->id()
            ? PrivateMarketplaceMemberModel::query()->findOrFail($member->id())
            : new PrivateMarketplaceMemberModel();

        $model->private_marketplace_id = $member->privateMarketplaceId();
        $model->business_id = $member->businessId();
        $model->status = $member->status()->value;
        $model->invited_at = $member->invitedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(PrivateMarketplaceMemberModel $model): PrivateMarketplaceMemberEntity
    {
        return new PrivateMarketplaceMemberEntity(
            id: $model->id,
            privateMarketplaceId: $model->private_marketplace_id,
            businessId: $model->business_id,
            status: PrivateMarketplaceMemberStatus::from($model->status),
            invitedAt: DateTimeImmutable::createFromInterface($model->invited_at),
        );
    }
}
