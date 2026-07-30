<?php

namespace App\Core\Infrastructure\Repositories;

use App\Core\Domain\Entities\Tenant as TenantEntity;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Core\Domain\ValueObjects\TenantStatus;
use App\Core\Infrastructure\Models\Tenant as TenantModel;
use DateTimeImmutable;

class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function findById(int $id): ?TenantEntity
    {
        $model = TenantModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findBySlug(string $slug): ?TenantEntity
    {
        $model = TenantModel::query()->where('slug', $slug)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function slugExists(string $slug): bool
    {
        return TenantModel::query()->where('slug', $slug)->exists();
    }

    public function save(TenantEntity $tenant): TenantEntity
    {
        $model = $tenant->id()
            ? TenantModel::query()->findOrFail($tenant->id())
            : new TenantModel();

        $model->name = $tenant->name();
        $model->slug = $tenant->slug();
        $model->status = $tenant->status()->value;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(TenantModel $model): TenantEntity
    {
        return new TenantEntity(
            id: $model->id,
            name: $model->name,
            slug: $model->slug,
            status: TenantStatus::from($model->status),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
