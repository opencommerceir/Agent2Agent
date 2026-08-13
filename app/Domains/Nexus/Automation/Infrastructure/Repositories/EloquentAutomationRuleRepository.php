<?php

namespace App\Domains\Nexus\Automation\Infrastructure\Repositories;

use App\Domains\Nexus\Automation\Domain\Entities\AutomationRule as AutomationRuleEntity;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRuleRepositoryInterface;
use App\Domains\Nexus\Automation\Domain\ValueObjects\AutomationRuleStatus;
use App\Domains\Nexus\Automation\Domain\ValueObjects\AutomationRuleType;
use App\Domains\Nexus\Automation\Infrastructure\Models\AutomationRule as AutomationRuleModel;
use DateTimeImmutable;

class EloquentAutomationRuleRepository implements AutomationRuleRepositoryInterface
{
    public function findById(int $id): ?AutomationRuleEntity
    {
        $model = AutomationRuleModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByBusinessId(int $businessId): array
    {
        return AutomationRuleModel::query()
            ->where('business_id', $businessId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (AutomationRuleModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findActive(): array
    {
        return AutomationRuleModel::query()
            ->where('status', AutomationRuleStatus::Active->value)
            ->orderBy('id')
            ->get()
            ->map(fn (AutomationRuleModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(AutomationRuleEntity $rule): AutomationRuleEntity
    {
        $model = $rule->id()
            ? AutomationRuleModel::query()->findOrFail($rule->id())
            : new AutomationRuleModel();

        $model->business_id = $rule->businessId();
        $model->type = $rule->type()->value;
        $model->config = $rule->config();
        $model->status = $rule->status()->value;
        $model->last_triggered_at = $rule->lastTriggeredAt();
        $model->save();

        return $this->toEntity($model);
    }

    public function delete(int $id): void
    {
        AutomationRuleModel::query()->where('id', $id)->delete();
    }

    private function toEntity(AutomationRuleModel $model): AutomationRuleEntity
    {
        return new AutomationRuleEntity(
            id: $model->id,
            businessId: $model->business_id,
            type: AutomationRuleType::from($model->type),
            config: $model->config,
            status: AutomationRuleStatus::from($model->status),
            lastTriggeredAt: $model->last_triggered_at ? DateTimeImmutable::createFromInterface($model->last_triggered_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
