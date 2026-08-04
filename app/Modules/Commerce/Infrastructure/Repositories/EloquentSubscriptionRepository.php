<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Subscription as SubscriptionEntity;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionStatus;
use App\Modules\Commerce\Infrastructure\Models\Subscription as SubscriptionModel;
use DateTimeImmutable;

class EloquentSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?SubscriptionEntity
    {
        $model = SubscriptionModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function listByTenant(int $tenantId, ?SubscriptionStatus $status = null, ?int $customerId = null): array
    {
        $builder = SubscriptionModel::query()->where('tenant_id', $tenantId);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        if ($customerId !== null) {
            $builder->where('customer_id', $customerId);
        }

        return $builder->orderBy('id')
            ->get()
            ->map(fn (SubscriptionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findDueForRenewal(int $tenantId, DateTimeImmutable $before): array
    {
        return SubscriptionModel::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [SubscriptionStatus::Trial->value, SubscriptionStatus::Active->value])
            ->where('current_period_end', '<=', $before)
            ->orderBy('id')
            ->get()
            ->map(fn (SubscriptionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(SubscriptionEntity $subscription): SubscriptionEntity
    {
        $model = $subscription->id()
            ? SubscriptionModel::query()->where('tenant_id', $subscription->tenantId())->findOrFail($subscription->id())
            : new SubscriptionModel();

        $model->tenant_id = $subscription->tenantId();
        $model->customer_id = $subscription->customerId();
        $model->subscription_plan_id = $subscription->subscriptionPlanId();
        $model->status = $subscription->status()->value;
        $model->current_period_start = $subscription->currentPeriodStart();
        $model->current_period_end = $subscription->currentPeriodEnd();
        $model->trial_start = $subscription->trialStart();
        $model->trial_end = $subscription->trialEnd();
        $model->paused_at = $subscription->pausedAt();
        $model->cancelled_at = $subscription->cancelledAt();
        $model->cancel_at_period_end = $subscription->cancelAtPeriodEnd();
        $model->payment_method_id = $subscription->paymentMethodId();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(SubscriptionModel $model): SubscriptionEntity
    {
        return new SubscriptionEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            customerId: $model->customer_id,
            subscriptionPlanId: $model->subscription_plan_id,
            status: SubscriptionStatus::from($model->status),
            currentPeriodStart: DateTimeImmutable::createFromInterface($model->current_period_start),
            currentPeriodEnd: DateTimeImmutable::createFromInterface($model->current_period_end),
            trialStart: $model->trial_start ? DateTimeImmutable::createFromInterface($model->trial_start) : null,
            trialEnd: $model->trial_end ? DateTimeImmutable::createFromInterface($model->trial_end) : null,
            pausedAt: $model->paused_at ? DateTimeImmutable::createFromInterface($model->paused_at) : null,
            cancelledAt: $model->cancelled_at ? DateTimeImmutable::createFromInterface($model->cancelled_at) : null,
            cancelAtPeriodEnd: $model->cancel_at_period_end,
            paymentMethodId: $model->payment_method_id,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            updatedAt: DateTimeImmutable::createFromInterface($model->updated_at),
        );
    }
}
