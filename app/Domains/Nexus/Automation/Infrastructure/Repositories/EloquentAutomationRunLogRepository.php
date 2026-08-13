<?php

namespace App\Domains\Nexus\Automation\Infrastructure\Repositories;

use App\Domains\Nexus\Automation\Domain\Entities\AutomationRunLog as AutomationRunLogEntity;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRunLogRepositoryInterface;
use App\Domains\Nexus\Automation\Domain\ValueObjects\AutomationRunOutcome;
use App\Domains\Nexus\Automation\Infrastructure\Models\AutomationRunLog as AutomationRunLogModel;
use DateTimeImmutable;

class EloquentAutomationRunLogRepository implements AutomationRunLogRepositoryInterface
{
    public function save(AutomationRunLogEntity $log): AutomationRunLogEntity
    {
        $model = new AutomationRunLogModel();
        $model->automation_rule_id = $log->automationRuleId();
        $model->business_id = $log->businessId();
        $model->outcome = $log->outcome()->value;
        $model->detail = $log->detail();
        $model->created_at = $log->createdAt();
        $model->save();

        return $this->toEntity($model);
    }

    public function findByRuleId(int $ruleId): array
    {
        return AutomationRunLogModel::query()
            ->where('automation_rule_id', $ruleId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (AutomationRunLogModel $model) => $this->toEntity($model))
            ->all();
    }

    private function toEntity(AutomationRunLogModel $model): AutomationRunLogEntity
    {
        return new AutomationRunLogEntity(
            id: $model->id,
            automationRuleId: $model->automation_rule_id,
            businessId: $model->business_id,
            outcome: AutomationRunOutcome::from($model->outcome),
            detail: $model->detail,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
