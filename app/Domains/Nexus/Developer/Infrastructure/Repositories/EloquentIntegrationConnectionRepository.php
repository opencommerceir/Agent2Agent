<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\IntegrationConnection as ConnectionEntity;
use App\Domains\Nexus\Developer\Domain\Repositories\IntegrationConnectionRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\IntegrationCategory;
use App\Domains\Nexus\Developer\Infrastructure\Models\IntegrationConnection as ConnectionModel;
use DateTimeImmutable;

class EloquentIntegrationConnectionRepository implements IntegrationConnectionRepositoryInterface
{
    public function findById(int $id): ?ConnectionEntity
    {
        $model = ConnectionModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByBusinessId(int $businessId): array
    {
        return ConnectionModel::query()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ConnectionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ConnectionEntity $connection): ConnectionEntity
    {
        $model = $connection->id()
            ? ConnectionModel::query()->findOrFail($connection->id())
            : new ConnectionModel();

        $model->business_id = $connection->businessId();
        $model->category = $connection->category()->value;
        $model->name = $connection->name();
        $model->target_url = $connection->targetUrl();
        $model->auth_token = $connection->authToken();
        $model->field_mapping = $connection->fieldMapping();
        $model->revoked_at = $connection->revokedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ConnectionModel $model): ConnectionEntity
    {
        return new ConnectionEntity(
            id: $model->id,
            businessId: $model->business_id,
            category: IntegrationCategory::from($model->category),
            name: $model->name,
            targetUrl: $model->target_url,
            authToken: $model->auth_token,
            fieldMapping: $model->field_mapping ?? [],
            revokedAt: $model->revoked_at ? DateTimeImmutable::createFromInterface($model->revoked_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
