<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\PaymentSession as PaymentSessionEntity;
use App\Modules\Commerce\Domain\Repositories\PaymentSessionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentSessionStatus;
use App\Modules\Commerce\Infrastructure\Models\PaymentSession as PaymentSessionModel;
use DateTimeImmutable;

class EloquentPaymentSessionRepository implements PaymentSessionRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?PaymentSessionEntity
    {
        $model = PaymentSessionModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByIdUnscoped(int $id): ?PaymentSessionEntity
    {
        $model = PaymentSessionModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByProviderReference(string $gateway, string $providerReference): ?PaymentSessionEntity
    {
        $model = PaymentSessionModel::query()
            ->where('gateway', $gateway)
            ->where('provider_reference', $providerReference)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(PaymentSessionEntity $session): PaymentSessionEntity
    {
        $isNew = $session->id() === null;

        $model = $isNew
            ? new PaymentSessionModel()
            : PaymentSessionModel::query()->where('tenant_id', $session->tenantId())->findOrFail($session->id());

        $model->tenant_id = $session->tenantId();
        $model->cart_id = $session->cartId();
        $model->agent_id = $session->agentId();
        $model->gateway = $session->gateway();
        $model->provider_reference = $session->providerReference();
        $model->total_amount = $session->total()->amount();
        $model->tax_amount = $session->tax()->amount();
        $model->discount_amount = $session->discount()->amount();
        $model->currency = $session->total()->currency();
        $model->status = $session->status()->value;
        $model->coupon_code = $session->couponCode();
        $model->customer_id = $session->customerId();
        $model->notes = $session->notes();
        $model->region = $session->region();
        $model->order_id = $session->orderId();
        $model->completed_at = $session->completedAt();
        $model->save();

        if ($isNew) {
            $session->assignId($model->id);
        }

        return $this->toEntity($model);
    }

    private function toEntity(PaymentSessionModel $model): PaymentSessionEntity
    {
        return PaymentSessionEntity::reconstruct(
            id: $model->id,
            tenantId: $model->tenant_id,
            cartId: $model->cart_id,
            agentId: $model->agent_id,
            gateway: $model->gateway,
            providerReference: $model->provider_reference,
            total: Money::fromAmount($model->total_amount, $model->currency),
            tax: Money::fromAmount($model->tax_amount, $model->currency),
            discount: Money::fromAmount($model->discount_amount, $model->currency),
            status: PaymentSessionStatus::from($model->status),
            couponCode: $model->coupon_code,
            customerId: $model->customer_id,
            notes: $model->notes,
            region: $model->region,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            completedAt: $model->completed_at ? DateTimeImmutable::createFromInterface($model->completed_at) : null,
            orderId: $model->order_id,
        );
    }
}
