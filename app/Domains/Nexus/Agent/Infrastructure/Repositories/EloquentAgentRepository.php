<?php

namespace App\Domains\Nexus\Agent\Infrastructure\Repositories;

use App\Domains\Nexus\Agent\Domain\Entities\Agent as AgentEntity;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Agent\Infrastructure\Models\Agent as AgentModel;
use DateTimeImmutable;

class EloquentAgentRepository implements AgentRepositoryInterface
{
    public function findById(int $id): ?AgentEntity
    {
        $model = AgentModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByBusinessId(int $businessId): ?AgentEntity
    {
        $model = AgentModel::query()->where('business_id', $businessId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(AgentEntity $agent): AgentEntity
    {
        $model = $agent->id()
            ? AgentModel::query()->findOrFail($agent->id())
            : new AgentModel();

        $model->business_id = $agent->businessId();
        $model->core_agent_id = $agent->coreAgentId();
        $model->name_fa = $agent->nameFa();
        $model->name_en = $agent->nameEn();
        $model->personality = $agent->personality();
        $model->tone = $agent->tone();
        $model->authority_limits = $agent->authorityLimits();
        $model->strategies = $agent->strategies();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(AgentModel $model): AgentEntity
    {
        return new AgentEntity(
            id: $model->id,
            businessId: $model->business_id,
            coreAgentId: $model->core_agent_id,
            nameFa: $model->name_fa,
            nameEn: $model->name_en,
            personality: $model->personality,
            tone: $model->tone,
            authorityLimits: $model->authority_limits,
            strategies: $model->strategies,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
