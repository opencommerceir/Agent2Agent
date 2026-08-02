<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Category as CategoryEntity;
use App\Modules\Commerce\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Commerce\Infrastructure\Models\Category as CategoryModel;
use DateTimeImmutable;

class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?CategoryEntity
    {
        $model = CategoryModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByName(string $name, int $tenantId): ?CategoryEntity
    {
        $model = CategoryModel::query()->where('tenant_id', $tenantId)->where('name', $name)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function slugExists(string $slug, int $tenantId): bool
    {
        return CategoryModel::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists();
    }

    public function save(CategoryEntity $category): CategoryEntity
    {
        $model = $category->id()
            ? CategoryModel::query()->where('tenant_id', $category->tenantId())->findOrFail($category->id())
            : new CategoryModel();

        $model->tenant_id = $category->tenantId();
        $model->name = $category->name();
        $model->slug = $category->slug();
        $model->description = $category->description();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(CategoryModel $model): CategoryEntity
    {
        return new CategoryEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            slug: $model->slug,
            description: $model->description,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
