<?php

namespace App\Core\Infrastructure\Repositories;

use App\Core\Domain\Entities\Agent as AgentEntity;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\ValueObjects\AgentStatus;
use App\Core\Domain\ValueObjects\AgentType;
use App\Core\Infrastructure\Models\Agent as AgentModel;
use DateTimeImmutable;

class EloquentAgentRepository implements AgentRepositoryInterface
{
    public function findById(int $id): ?AgentEntity
    {
        $model = AgentModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function all(): array
    {
        return AgentModel::query()
            ->orderBy('id')
            ->get()
            ->map(fn (AgentModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(AgentEntity $agent): AgentEntity
    {
        $model = $agent->id()
            ? AgentModel::query()->findOrFail($agent->id())
            : new AgentModel();

        $model->tenant_id = $agent->tenantId();
        $model->organization_id = $agent->organizationId();
        $model->name = $agent->name();
        $model->type = $agent->type()->value;
        $model->status = $agent->status()->value;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(AgentModel $model): AgentEntity
    {
        return new AgentEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            organizationId: $model->organization_id,
            name: $model->name,
            type: AgentType::from($model->type),
            status: AgentStatus::from($model->status),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
