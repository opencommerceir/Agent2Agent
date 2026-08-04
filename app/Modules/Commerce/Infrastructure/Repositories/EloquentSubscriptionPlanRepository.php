<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\SubscriptionPlan as SubscriptionPlanEntity;
use App\Modules\Commerce\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\BillingCycle;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\TrialPeriod;
use App\Modules\Commerce\Infrastructure\Models\SubscriptionPlan as SubscriptionPlanModel;
use DateTimeImmutable;

class EloquentSubscriptionPlanRepository implements SubscriptionPlanRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?SubscriptionPlanEntity
    {
        $model = SubscriptionPlanModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function listByTenant(int $tenantId, ?bool $isActive = null): array
    {
        $builder = SubscriptionPlanModel::query()->where('tenant_id', $tenantId);

        if ($isActive !== null) {
            $builder->where('is_active', $isActive);
        }

        return $builder->orderBy('id')
            ->get()
            ->map(fn (SubscriptionPlanModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(SubscriptionPlanEntity $plan): SubscriptionPlanEntity
    {
        $model = $plan->id()
            ? SubscriptionPlanModel::query()->where('tenant_id', $plan->tenantId())->findOrFail($plan->id())
            : new SubscriptionPlanModel();

        $model->tenant_id = $plan->tenantId();
        $model->name = $plan->name();
        $model->description = $plan->description();
        $model->billing_cycle = $plan->billingCycle()->value;
        $model->price_amount = $plan->price()->amount();
        $model->price_currency = $plan->price()->currency();
        $model->trial_days = $plan->trialPeriod()->days();
        $model->features = $plan->features();
        $model->is_active = $plan->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(SubscriptionPlanModel $model): SubscriptionPlanEntity
    {
        return new SubscriptionPlanEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            description: $model->description,
            billingCycle: BillingCycle::from($model->billing_cycle),
            price: Money::fromAmount($model->price_amount, $model->price_currency),
            trialPeriod: new TrialPeriod($model->trial_days),
            features: $model->features ?? [],
            isActive: $model->is_active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
