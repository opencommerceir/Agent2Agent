<?php

namespace App\Core\Infrastructure\Repositories;

use App\Core\Domain\Entities\Organization as OrganizationEntity;
use App\Core\Domain\Repositories\OrganizationRepositoryInterface;
use App\Core\Domain\ValueObjects\OrganizationStatus;
use App\Core\Infrastructure\Models\Organization as OrganizationModel;
use DateTimeImmutable;

class EloquentOrganizationRepository implements OrganizationRepositoryInterface
{
    public function findById(int $id): ?OrganizationEntity
    {
        $model = OrganizationModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findBySlug(int $tenantId, string $slug): ?OrganizationEntity
    {
        $model = OrganizationModel::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function existsBySlug(int $tenantId, string $slug): bool
    {
        return OrganizationModel::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->exists();
    }

    public function save(OrganizationEntity $organization): OrganizationEntity
    {
        $model = $organization->id()
            ? OrganizationModel::query()->findOrFail($organization->id())
            : new OrganizationModel();

        $model->tenant_id = $organization->tenantId();
        $model->name = $organization->name();
        $model->slug = $organization->slug();
        $model->owner_user_id = $organization->ownerUserId();
        $model->status = $organization->status()->value;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(OrganizationModel $model): OrganizationEntity
    {
        return new OrganizationEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            slug: $model->slug,
            ownerUserId: $model->owner_user_id,
            status: OrganizationStatus::from($model->status),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
