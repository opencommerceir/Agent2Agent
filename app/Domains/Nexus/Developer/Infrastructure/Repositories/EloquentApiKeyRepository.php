<?php

namespace App\Domains\Nexus\Developer\Infrastructure\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\ApiKey as ApiKeyEntity;
use App\Domains\Nexus\Developer\Domain\Repositories\ApiKeyRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use App\Domains\Nexus\Developer\Infrastructure\Models\ApiKey as ApiKeyModel;
use DateTimeImmutable;

class EloquentApiKeyRepository implements ApiKeyRepositoryInterface
{
    public function findById(int $id): ?ApiKeyEntity
    {
        $model = ApiKeyModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByHash(string $keyHash): ?ApiKeyEntity
    {
        $model = ApiKeyModel::query()->where('key_hash', $keyHash)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByBusinessId(int $businessId): array
    {
        return ApiKeyModel::query()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiKeyModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ApiKeyEntity $apiKey): ApiKeyEntity
    {
        $model = $apiKey->id()
            ? ApiKeyModel::query()->findOrFail($apiKey->id())
            : new ApiKeyModel();

        $model->business_id = $apiKey->businessId();
        $model->key_hash = $apiKey->keyHash();
        $model->key_prefix = $apiKey->keyPrefix();
        $model->label = $apiKey->label();
        $model->scopes = array_map(fn (ApiKeyScope $scope) => $scope->value, $apiKey->scopes());
        $model->last_used_at = $apiKey->lastUsedAt();
        $model->expires_at = $apiKey->expiresAt();
        $model->revoked_at = $apiKey->revokedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ApiKeyModel $model): ApiKeyEntity
    {
        return new ApiKeyEntity(
            id: $model->id,
            businessId: $model->business_id,
            keyHash: $model->key_hash,
            keyPrefix: $model->key_prefix,
            label: $model->label,
            scopes: array_map(fn (string $value) => ApiKeyScope::from($value), $model->scopes ?? []),
            lastUsedAt: $model->last_used_at ? DateTimeImmutable::createFromInterface($model->last_used_at) : null,
            expiresAt: $model->expires_at ? DateTimeImmutable::createFromInterface($model->expires_at) : null,
            revokedAt: $model->revoked_at ? DateTimeImmutable::createFromInterface($model->revoked_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
