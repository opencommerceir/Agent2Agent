<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Payment as PaymentEntity;
use App\Modules\Commerce\Domain\Repositories\PaymentRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use App\Modules\Commerce\Domain\ValueObjects\PaymentStatus;
use App\Modules\Commerce\Infrastructure\Models\Payment as PaymentModel;
use DateTimeImmutable;

class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?PaymentEntity
    {
        $model = PaymentModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByOrderId(int $orderId, int $tenantId): ?PaymentEntity
    {
        $model = PaymentModel::query()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(PaymentEntity $payment): PaymentEntity
    {
        $model = $payment->id()
            ? PaymentModel::query()->where('tenant_id', $payment->tenantId())->findOrFail($payment->id())
            : new PaymentModel();

        $model->tenant_id = $payment->tenantId();
        $model->order_id = $payment->orderId();
        $model->amount = $payment->amount()->amount();
        $model->currency = $payment->amount()->currency();
        $model->payment_method = $payment->method()->value;
        $model->status = $payment->status()->value;
        $model->transaction_id = $payment->transactionId();
        $model->gateway_response = $payment->gatewayResponse();
        $model->gateway = $payment->gateway();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(PaymentModel $model): PaymentEntity
    {
        return new PaymentEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            orderId: $model->order_id,
            amount: Money::fromAmount($model->amount, $model->currency),
            method: PaymentMethod::from($model->payment_method),
            status: PaymentStatus::from($model->status),
            transactionId: $model->transaction_id,
            gatewayResponse: $model->gateway_response ?? [],
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            gateway: $model->gateway,
        );
    }
}
