<?php

namespace App\Domains\Nexus\Credit\Infrastructure\Repositories;

use App\Domains\Nexus\Credit\Domain\Entities\CreditPurchaseSession as CreditPurchaseSessionEntity;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditPurchaseSessionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPackage;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPurchaseSessionStatus;
use App\Domains\Nexus\Credit\Domain\ValueObjects\Money;
use App\Domains\Nexus\Credit\Infrastructure\Models\CreditPurchaseSession as CreditPurchaseSessionModel;
use DateTimeImmutable;

class EloquentCreditPurchaseSessionRepository implements CreditPurchaseSessionRepositoryInterface
{
    public function findById(int $id, int $businessId): ?CreditPurchaseSessionEntity
    {
        $model = CreditPurchaseSessionModel::query()
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByIdUnscoped(int $id): ?CreditPurchaseSessionEntity
    {
        $model = CreditPurchaseSessionModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(CreditPurchaseSessionEntity $session): CreditPurchaseSessionEntity
    {
        $model = $session->id()
            ? CreditPurchaseSessionModel::query()->findOrFail($session->id())
            : new CreditPurchaseSessionModel();

        $model->business_id = $session->businessId();
        $model->gateway = $session->gateway();
        $model->provider_reference = $session->providerReference();
        $model->package = $session->package()->value;
        $model->total_amount = $session->total()->amount();
        $model->total_currency = $session->total()->currency();
        $model->status = $session->status()->value;
        $model->completed_at = $session->completedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(CreditPurchaseSessionModel $model): CreditPurchaseSessionEntity
    {
        return CreditPurchaseSessionEntity::reconstruct(
            id: $model->id,
            businessId: $model->business_id,
            gateway: $model->gateway,
            providerReference: $model->provider_reference,
            package: CreditPackage::from($model->package),
            total: Money::fromAmount($model->total_amount, $model->total_currency),
            status: CreditPurchaseSessionStatus::from($model->status),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            completedAt: $model->completed_at ? DateTimeImmutable::createFromInterface($model->completed_at) : null,
        );
    }
}
