<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice as SubscriptionInvoiceEntity;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionInvoiceStatus;
use App\Modules\Commerce\Infrastructure\Models\SubscriptionInvoice as SubscriptionInvoiceModel;
use DateTimeImmutable;

class EloquentSubscriptionInvoiceRepository implements SubscriptionInvoiceRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?SubscriptionInvoiceEntity
    {
        $model = SubscriptionInvoiceModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function listBySubscription(int $subscriptionId, int $tenantId): array
    {
        return SubscriptionInvoiceModel::query()
            ->where('tenant_id', $tenantId)
            ->where('subscription_id', $subscriptionId)
            ->orderBy('id')
            ->get()
            ->map(fn (SubscriptionInvoiceModel $model) => $this->toEntity($model))
            ->all();
    }

    /**
     * Filters in PHP against SubscriptionInvoice::isRetryDue() rather than
     * re-deriving the "failed_at + intervalDays*retryCount" window in raw
     * SQL — the retry set is bounded (Failed rows only, capped at 3
     * retries each) and reusing the entity's own predicate keeps the "what
     * counts as due" logic in exactly one place, the same reasoning
     * BulkOperation's own item-outcome logic isn't duplicated into a query
     * builder either.
     */
    public function findDueForRetry(int $tenantId, DateTimeImmutable $before, int $intervalDays = 3): array
    {
        return SubscriptionInvoiceModel::query()
            ->where('tenant_id', $tenantId)
            ->where('status', SubscriptionInvoiceStatus::Failed->value)
            ->where('retry_count', '<', 3)
            ->orderBy('id')
            ->get()
            ->map(fn (SubscriptionInvoiceModel $model) => $this->toEntity($model))
            ->filter(fn (SubscriptionInvoiceEntity $invoice) => $invoice->isRetryDue($before, $intervalDays))
            ->values()
            ->all();
    }

    public function save(SubscriptionInvoiceEntity $invoice): SubscriptionInvoiceEntity
    {
        $model = $invoice->id()
            ? SubscriptionInvoiceModel::query()->where('tenant_id', $invoice->tenantId())->findOrFail($invoice->id())
            : new SubscriptionInvoiceModel();

        $model->tenant_id = $invoice->tenantId();
        $model->subscription_id = $invoice->subscriptionId();
        $model->order_id = $invoice->orderId();
        $model->amount = $invoice->amount()->amount();
        $model->currency = $invoice->amount()->currency();
        $model->status = $invoice->status()->value;
        $model->due_date = $invoice->dueDate();
        $model->paid_at = $invoice->paidAt();
        $model->failed_at = $invoice->failedAt();
        $model->retry_count = $invoice->retryCount();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(SubscriptionInvoiceModel $model): SubscriptionInvoiceEntity
    {
        return new SubscriptionInvoiceEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            subscriptionId: $model->subscription_id,
            orderId: $model->order_id,
            amount: Money::fromAmount($model->amount, $model->currency),
            status: SubscriptionInvoiceStatus::from($model->status),
            dueDate: DateTimeImmutable::createFromInterface($model->due_date),
            paidAt: $model->paid_at ? DateTimeImmutable::createFromInterface($model->paid_at) : null,
            failedAt: $model->failed_at ? DateTimeImmutable::createFromInterface($model->failed_at) : null,
            retryCount: $model->retry_count,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
