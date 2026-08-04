<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\DiscountRule as DiscountRuleEntity;
use App\Modules\Commerce\Domain\Entities\DiscountRuleCondition;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\DiscountCondition;
use App\Modules\Commerce\Domain\ValueObjects\DiscountPriority;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Stackability;
use App\Modules\Commerce\Infrastructure\Models\DiscountRule as DiscountRuleModel;
use App\Modules\Commerce\Infrastructure\Models\DiscountRuleCondition as DiscountRuleConditionModel;
use DateTimeImmutable;

/**
 * Never deletes-and-reinserts conditions, and only ever inserts them once
 * (when $isNew) — DiscountRuleCondition rows are immutable, the same
 * pattern EloquentWarehouseTransferRepository already establishes for
 * WarehouseTransferItem (§7.22).
 */
class EloquentDiscountRuleRepository implements DiscountRuleRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?DiscountRuleEntity
    {
        $model = DiscountRuleModel::query()->with('conditions')->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function listByTenant(int $tenantId, ?bool $isActive = null): array
    {
        $builder = DiscountRuleModel::query()->with('conditions')->where('tenant_id', $tenantId);

        if ($isActive !== null) {
            $builder->where('is_active', $isActive);
        }

        return $builder->orderBy('priority', 'desc')
            ->get()
            ->map(fn (DiscountRuleModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(DiscountRuleEntity $rule): DiscountRuleEntity
    {
        $isNew = $rule->id() === null;

        $model = $isNew
            ? new DiscountRuleModel()
            : DiscountRuleModel::query()->where('tenant_id', $rule->tenantId())->findOrFail($rule->id());

        $model->tenant_id = $rule->tenantId();
        $model->name = $rule->name();
        $model->description = $rule->description();
        $model->discount_type = $rule->discountType()->value;
        $model->discount_value = $rule->discountValue();
        $model->priority = $rule->priority()->value();
        $model->stackability = $rule->stackability()->value;
        $model->starts_at = $rule->startsAt();
        $model->expires_at = $rule->expiresAt();
        $model->is_active = $rule->isActive();
        $model->max_uses = $rule->maxUses();
        $model->used_count = $rule->usedCount();
        $model->save();

        if ($isNew) {
            foreach ($rule->conditions() as $condition) {
                $model->conditions()->create([
                    'condition_type' => $condition->type()->value,
                    'condition_value' => $condition->value(),
                    'created_at' => now(),
                ]);
            }
        }

        return $this->toEntity($model->fresh('conditions'));
    }

    public function delete(int $id, int $tenantId): void
    {
        DiscountRuleModel::query()->where('tenant_id', $tenantId)->where('id', $id)->delete();
    }

    private function toEntity(DiscountRuleModel $model): DiscountRuleEntity
    {
        $conditions = $model->conditions->map(fn (DiscountRuleConditionModel $conditionModel) => new DiscountRuleCondition(
            type: DiscountCondition::from($conditionModel->condition_type),
            value: $conditionModel->condition_value,
        ))->all();

        return new DiscountRuleEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            description: $model->description,
            discountType: DiscountType::from($model->discount_type),
            discountValue: $model->discount_value,
            priority: new DiscountPriority($model->priority),
            stackability: Stackability::from($model->stackability),
            conditions: $conditions,
            startsAt: DateTimeImmutable::createFromInterface($model->starts_at),
            expiresAt: $model->expires_at ? DateTimeImmutable::createFromInterface($model->expires_at) : null,
            isActive: $model->is_active,
            maxUses: $model->max_uses,
            usedCount: $model->used_count,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            updatedAt: DateTimeImmutable::createFromInterface($model->updated_at),
        );
    }
}
