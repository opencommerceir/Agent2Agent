<?php

namespace App\Core\Infrastructure\Repositories;

use App\Core\Domain\Entities\AgentToken as AgentTokenEntity;
use App\Core\Domain\Repositories\AgentTokenRepositoryInterface;
use App\Core\Infrastructure\Models\AgentToken as AgentTokenModel;
use DateTimeImmutable;

class EloquentAgentTokenRepository implements AgentTokenRepositoryInterface
{
    public function findByHash(string $tokenHash): ?AgentTokenEntity
    {
        $model = AgentTokenModel::query()->where('token_hash', $tokenHash)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(AgentTokenEntity $token): AgentTokenEntity
    {
        $model = $token->id()
            ? AgentTokenModel::query()->findOrFail($token->id())
            : new AgentTokenModel();

        $model->agent_id = $token->agentId();
        $model->token_hash = $token->tokenHash();
        $model->label = $token->label();
        $model->last_used_at = $token->lastUsedAt();
        $model->expires_at = $token->expiresAt();
        $model->revoked_at = $token->revokedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(AgentTokenModel $model): AgentTokenEntity
    {
        return new AgentTokenEntity(
            id: $model->id,
            agentId: $model->agent_id,
            tokenHash: $model->token_hash,
            label: $model->label,
            lastUsedAt: $model->last_used_at ? DateTimeImmutable::createFromInterface($model->last_used_at) : null,
            expiresAt: $model->expires_at ? DateTimeImmutable::createFromInterface($model->expires_at) : null,
            revokedAt: $model->revoked_at ? DateTimeImmutable::createFromInterface($model->revoked_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
