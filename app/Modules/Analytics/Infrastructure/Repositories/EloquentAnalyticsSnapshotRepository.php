<?php

namespace App\Modules\Analytics\Infrastructure\Repositories;

use App\Modules\Analytics\Domain\Entities\AnalyticsSnapshot as AnalyticsSnapshotEntity;
use App\Modules\Analytics\Domain\Repositories\AnalyticsSnapshotRepositoryInterface;
use App\Modules\Analytics\Domain\ValueObjects\Money;
use App\Modules\Analytics\Infrastructure\Models\AnalyticsSnapshot as AnalyticsSnapshotModel;
use DateTimeImmutable;

class EloquentAnalyticsSnapshotRepository implements AnalyticsSnapshotRepositoryInterface
{
    public function findByDate(int $tenantId, DateTimeImmutable $date): ?AnalyticsSnapshotEntity
    {
        $model = AnalyticsSnapshotModel::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('snapshot_date', $date->format('Y-m-d'))
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function listByTenant(int $tenantId, int $limit): array
    {
        return AnalyticsSnapshotModel::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('snapshot_date')
            ->limit($limit)
            ->get()
            ->map(fn (AnalyticsSnapshotModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(AnalyticsSnapshotEntity $snapshot): AnalyticsSnapshotEntity
    {
        $model = AnalyticsSnapshotModel::query()
            ->where('tenant_id', $snapshot->tenantId())
            ->whereDate('snapshot_date', $snapshot->snapshotDate()->format('Y-m-d'))
            ->first() ?? new AnalyticsSnapshotModel();

        $model->tenant_id = $snapshot->tenantId();
        $model->snapshot_date = $snapshot->snapshotDate()->format('Y-m-d');
        $model->total_revenue_amount = $snapshot->totalRevenue()->amount();
        $model->total_revenue_currency = $snapshot->totalRevenue()->currency();
        $model->total_orders = $snapshot->totalOrders();
        $model->total_customers = $snapshot->totalCustomers();
        $model->avg_order_value_amount = $snapshot->avgOrderValue()->amount();
        $model->conversion_rate = $snapshot->conversionRate();
        $model->top_products = $snapshot->topProducts();
        $model->top_customers = $snapshot->topCustomers();
        $model->created_at = $snapshot->createdAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(AnalyticsSnapshotModel $model): AnalyticsSnapshotEntity
    {
        return new AnalyticsSnapshotEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            snapshotDate: DateTimeImmutable::createFromInterface($model->snapshot_date),
            totalRevenue: Money::fromAmount($model->total_revenue_amount, $model->total_revenue_currency),
            totalOrders: $model->total_orders,
            totalCustomers: $model->total_customers,
            avgOrderValue: Money::fromAmount($model->avg_order_value_amount, $model->total_revenue_currency),
            conversionRate: $model->conversion_rate,
            topProducts: $model->top_products ?? [],
            topCustomers: $model->top_customers ?? [],
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
