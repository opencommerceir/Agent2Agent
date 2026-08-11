<?php

namespace App\Domains\Nexus\Business\Infrastructure\Repositories;

use App\Domains\Nexus\Business\Domain\Entities\Business as BusinessEntity;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Domain\ValueObjects\VerificationStatus;
use App\Domains\Nexus\Business\Infrastructure\Models\Business as BusinessModel;
use DateTimeImmutable;

class EloquentBusinessRepository implements BusinessRepositoryInterface
{
    public function findById(int $id): ?BusinessEntity
    {
        $model = BusinessModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByTenantId(int $tenantId): ?BusinessEntity
    {
        $model = BusinessModel::query()->where('tenant_id', $tenantId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(BusinessEntity $business): BusinessEntity
    {
        $model = $business->id()
            ? BusinessModel::query()->findOrFail($business->id())
            : new BusinessModel();

        $model->tenant_id = $business->tenantId();
        $model->organization_id = $business->organizationId();
        $model->name_fa = $business->nameFa();
        $model->name_en = $business->nameEn();
        $model->type = $business->type()->value;
        $model->industry = $business->industry()->value;
        $model->verification_status = $business->verificationStatus()->value;
        $model->logo_path = $business->logoPath();
        $model->documents = $business->documents();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(BusinessModel $model): BusinessEntity
    {
        return new BusinessEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            organizationId: $model->organization_id,
            nameFa: $model->name_fa,
            nameEn: $model->name_en,
            type: BusinessType::from($model->type),
            industry: Industry::from($model->industry),
            verificationStatus: VerificationStatus::from($model->verification_status),
            logoPath: $model->logo_path,
            documents: $model->documents,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
