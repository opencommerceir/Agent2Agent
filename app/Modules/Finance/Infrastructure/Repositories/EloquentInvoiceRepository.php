<?php

namespace App\Modules\Finance\Infrastructure\Repositories;

use App\Modules\Finance\Domain\Entities\Invoice as InvoiceEntity;
use App\Modules\Finance\Domain\Entities\InvoiceItem;
use App\Modules\Finance\Domain\Repositories\InvoiceRepositoryInterface;
use App\Modules\Finance\Domain\ValueObjects\InvoiceNumber;
use App\Modules\Finance\Domain\ValueObjects\InvoiceStatus;
use App\Modules\Finance\Domain\ValueObjects\Money;
use App\Modules\Finance\Infrastructure\Models\Invoice as InvoiceModel;
use App\Modules\Finance\Infrastructure\Models\InvoiceItem as InvoiceItemModel;
use DateTimeImmutable;

/**
 * Never deletes-and-reinserts items, and only ever inserts them once
 * (when $isNew) — Invoice items are immutable (mirrors
 * EloquentOrderRepository's own docblock).
 *
 * Every read method eager-loads `items` (Phase 4 Stage 8, Performance
 * Optimization, §7.20) — toEntity() always reads $model->items, so
 * list() was a real N+1 before this.
 */
class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?InvoiceEntity
    {
        $model = InvoiceModel::query()->with('items')->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function invoiceNumberExists(string $invoiceNumber, int $tenantId): bool
    {
        return InvoiceModel::query()
            ->where('tenant_id', $tenantId)
            ->where('invoice_number', $invoiceNumber)
            ->exists();
    }

    public function list(int $tenantId, ?InvoiceStatus $status, ?int $customerId, int $limit): array
    {
        $builder = InvoiceModel::query()->with('items')->where('tenant_id', $tenantId);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        if ($customerId !== null) {
            $builder->where('customer_id', $customerId);
        }

        return $builder->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (InvoiceModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(InvoiceEntity $invoice): InvoiceEntity
    {
        $isNew = $invoice->id() === null;

        $model = $isNew
            ? new InvoiceModel()
            : InvoiceModel::query()->where('tenant_id', $invoice->tenantId())->findOrFail($invoice->id());

        $model->tenant_id = $invoice->tenantId();
        $model->order_id = $invoice->orderId();
        $model->customer_id = $invoice->customerId();
        $model->invoice_number = $invoice->invoiceNumber()->value();
        $model->status = $invoice->status()->value;
        $model->subtotal_amount = $invoice->subtotal()->amount();
        $model->subtotal_currency = $invoice->subtotal()->currency();
        $model->tax_amount = $invoice->tax()->amount();
        $model->tax_currency = $invoice->tax()->currency();
        $model->total_amount = $invoice->total()->amount();
        $model->total_currency = $invoice->total()->currency();
        $model->issued_at = $invoice->issuedAt();
        $model->save();

        if ($isNew) {
            foreach ($invoice->items() as $item) {
                $model->items()->create([
                    'description' => $item->description(),
                    'quantity' => $item->quantity(),
                    'unit_price_amount' => $item->unitPrice()->amount(),
                    'unit_price_currency' => $item->unitPrice()->currency(),
                    'total_amount' => $item->totalAmount()->amount(),
                    'total_currency' => $item->totalAmount()->currency(),
                ]);
            }
        }

        return $this->toEntity($model->fresh('items'));
    }

    private function toEntity(InvoiceModel $model): InvoiceEntity
    {
        $items = $model->items->map(fn (InvoiceItemModel $itemModel) => InvoiceItem::create(
            description: $itemModel->description,
            quantity: $itemModel->quantity,
            unitPrice: Money::fromAmount($itemModel->unit_price_amount, $itemModel->unit_price_currency),
            totalAmount: Money::fromAmount($itemModel->total_amount, $itemModel->total_currency),
        ))->all();

        return new InvoiceEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            orderId: $model->order_id,
            customerId: $model->customer_id,
            invoiceNumber: new InvoiceNumber($model->invoice_number),
            status: InvoiceStatus::from($model->status),
            items: $items,
            subtotal: Money::fromAmount($model->subtotal_amount, $model->subtotal_currency),
            tax: Money::fromAmount($model->tax_amount, $model->tax_currency),
            total: Money::fromAmount($model->total_amount, $model->total_currency),
            issuedAt: $model->issued_at ? DateTimeImmutable::createFromInterface($model->issued_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
