<?php

namespace App\Core\Infrastructure\Repositories;

use App\Core\Domain\Entities\Permission as PermissionEntity;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Core\Infrastructure\Models\Permission as PermissionModel;
use DateTimeImmutable;

class EloquentPermissionRepository implements PermissionRepositoryInterface
{
    public function findById(int $id): ?PermissionEntity
    {
        $model = PermissionModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByKey(PermissionKey $key): ?PermissionEntity
    {
        $model = PermissionModel::query()->where('key', $key->value())->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(PermissionEntity $permission): PermissionEntity
    {
        $model = $permission->id()
            ? PermissionModel::query()->findOrFail($permission->id())
            : new PermissionModel();

        $model->key = $permission->key()->value();
        $model->description = $permission->description();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(PermissionModel $model): PermissionEntity
    {
        return new PermissionEntity(
            id: $model->id,
            key: new PermissionKey($model->key),
            description: $model->description,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
