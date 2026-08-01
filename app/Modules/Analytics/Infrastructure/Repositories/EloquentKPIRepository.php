<?php

namespace App\Modules\Analytics\Infrastructure\Repositories;

use App\Modules\Analytics\Domain\Entities\KPI as KPIEntity;
use App\Modules\Analytics\Domain\Entities\KPIValue as KPIValueEntity;
use App\Modules\Analytics\Domain\Repositories\KPIRepositoryInterface;
use App\Modules\Analytics\Domain\ValueObjects\KPIType;
use App\Modules\Analytics\Domain\ValueObjects\Money;
use App\Modules\Analytics\Domain\ValueObjects\TimePeriod;
use App\Modules\Analytics\Infrastructure\Models\KPI as KPIModel;
use App\Modules\Analytics\Infrastructure\Models\KPIValue as KPIValueModel;
use DateTimeImmutable;

class EloquentKPIRepository implements KPIRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?KPIEntity
    {
        $model = KPIModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByType(int $tenantId, KPIType $type): ?KPIEntity
    {
        $model = KPIModel::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type->value)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function listByTenant(int $tenantId, ?bool $isActive): array
    {
        $builder = KPIModel::query()->where('tenant_id', $tenantId);

        if ($isActive !== null) {
            $builder->where('is_active', $isActive);
        }

        return $builder->orderBy('id')->get()->map(fn (KPIModel $model) => $this->toEntity($model))->all();
    }

    public function save(KPIEntity $kpi): KPIEntity
    {
        $model = $kpi->id()
            ? KPIModel::query()->where('tenant_id', $kpi->tenantId())->findOrFail($kpi->id())
            : new KPIModel();

        $model->tenant_id = $kpi->tenantId();
        $model->type = $kpi->type()->value;
        $model->name = $kpi->name();
        $model->description = $kpi->description();
        $model->calculation_formula = $kpi->calculationFormula();
        $model->is_active = $kpi->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    public function saveValue(KPIValueEntity $value): KPIValueEntity
    {
        $model = new KPIValueModel();
        $model->tenant_id = $value->tenantId();
        $model->kpi_id = $value->kpiId();
        $model->value_amount = $value->value()->amount();
        $model->value_currency = $value->value()->currency();
        $model->time_period = $value->timePeriod()->value;
        $model->period_start = $value->periodStart()->format('Y-m-d');
        $model->period_end = $value->periodEnd()->format('Y-m-d');
        $model->calculated_at = $value->calculatedAt();
        $model->metadata = $value->metadata();
        $model->save();

        return $this->toValueEntity($model);
    }

    public function listValues(int $kpiId, int $tenantId, int $limit): array
    {
        return KPIValueModel::query()
            ->where('tenant_id', $tenantId)
            ->where('kpi_id', $kpiId)
            ->orderByDesc('calculated_at')
            ->limit($limit)
            ->get()
            ->map(fn (KPIValueModel $model) => $this->toValueEntity($model))
            ->all();
    }

    private function toEntity(KPIModel $model): KPIEntity
    {
        return new KPIEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            type: KPIType::from($model->type),
            name: $model->name,
            description: $model->description,
            calculationFormula: $model->calculation_formula ?? [],
            isActive: $model->is_active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    private function toValueEntity(KPIValueModel $model): KPIValueEntity
    {
        return new KPIValueEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            kpiId: $model->kpi_id,
            value: Money::fromAmount($model->value_amount, $model->value_currency),
            timePeriod: TimePeriod::from($model->time_period),
            periodStart: DateTimeImmutable::createFromInterface($model->period_start),
            periodEnd: DateTimeImmutable::createFromInterface($model->period_end),
            calculatedAt: DateTimeImmutable::createFromInterface($model->calculated_at),
            metadata: $model->metadata ?? [],
        );
    }
}
