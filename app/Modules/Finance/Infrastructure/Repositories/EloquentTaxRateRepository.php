<?php

namespace App\Modules\Finance\Infrastructure\Repositories;

use App\Modules\Finance\Domain\Entities\TaxRate as TaxRateEntity;
use App\Modules\Finance\Domain\Repositories\TaxRateRepositoryInterface;
use App\Modules\Finance\Domain\ValueObjects\TaxRegion;
use App\Modules\Finance\Infrastructure\Models\TaxRate as TaxRateModel;
use DateTimeImmutable;

class EloquentTaxRateRepository implements TaxRateRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?TaxRateEntity
    {
        $model = TaxRateModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByRegion(TaxRegion $region, int $tenantId): ?TaxRateEntity
    {
        $model = TaxRateModel::query()
            ->where('tenant_id', $tenantId)
            ->where('region', $region->value())
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function regionExists(TaxRegion $region, int $tenantId): bool
    {
        return TaxRateModel::query()
            ->where('tenant_id', $tenantId)
            ->where('region', $region->value())
            ->exists();
    }

    public function list(int $tenantId, ?bool $isActive): array
    {
        $builder = TaxRateModel::query()->where('tenant_id', $tenantId);

        if ($isActive !== null) {
            $builder->where('is_active', $isActive);
        }

        return $builder->orderBy('id')
            ->get()
            ->map(fn (TaxRateModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(TaxRateEntity $taxRate): TaxRateEntity
    {
        $model = $taxRate->id()
            ? TaxRateModel::query()->where('tenant_id', $taxRate->tenantId())->findOrFail($taxRate->id())
            : new TaxRateModel();

        $model->tenant_id = $taxRate->tenantId();
        $model->region = $taxRate->region()->value();
        $model->rate_percentage = $taxRate->ratePercentage();
        $model->is_active = $taxRate->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(TaxRateModel $model): TaxRateEntity
    {
        return new TaxRateEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            region: new TaxRegion($model->region),
            ratePercentage: $model->rate_percentage,
            isActive: $model->is_active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
