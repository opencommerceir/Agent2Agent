<?php

namespace App\Core\Infrastructure\Repositories;

use App\Core\Domain\Entities\Capability as CapabilityEntity;
use App\Core\Domain\Repositories\CapabilityRepositoryInterface;
use App\Core\Domain\ValueObjects\CapabilityName;
use App\Core\Domain\ValueObjects\CapabilitySchema;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Core\Infrastructure\Models\Capability as CapabilityModel;
use DateTimeImmutable;

class EloquentCapabilityRepository implements CapabilityRepositoryInterface
{
    public function findById(int $id): ?CapabilityEntity
    {
        $model = CapabilityModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByName(CapabilityName $name): ?CapabilityEntity
    {
        $model = CapabilityModel::query()->where('name', $name->value())->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function all(): array
    {
        return CapabilityModel::query()->get()
            ->map(fn (CapabilityModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(CapabilityEntity $capability): CapabilityEntity
    {
        $model = $capability->id()
            ? CapabilityModel::query()->findOrFail($capability->id())
            : new CapabilityModel();

        $model->name = $capability->name()->value();
        $model->description = $capability->description();
        $model->input_schema = $capability->inputSchema()->toArray();
        $model->output_schema = $capability->outputSchema()->toArray();
        $model->required_permissions = array_map(
            fn (PermissionKey $key) => $key->value(),
            $capability->requiredPermissions(),
        );
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(CapabilityModel $model): CapabilityEntity
    {
        return new CapabilityEntity(
            id: $model->id,
            name: new CapabilityName($model->name),
            description: $model->description ?? '',
            inputSchema: CapabilitySchema::fromArray($model->input_schema ?? []),
            outputSchema: CapabilitySchema::fromArray($model->output_schema ?? []),
            requiredPermissions: array_map(
                fn (string $key) => new PermissionKey($key),
                $model->required_permissions ?? [],
            ),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
