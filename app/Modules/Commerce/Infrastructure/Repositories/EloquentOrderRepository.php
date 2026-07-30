<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Order as OrderEntity;
use App\Modules\Commerce\Domain\Entities\OrderItem;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\OrderNumber;
use App\Modules\Commerce\Domain\ValueObjects\OrderStatus;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use App\Modules\Commerce\Infrastructure\Models\Order as OrderModel;
use App\Modules\Commerce\Infrastructure\Models\OrderItem as OrderItemModel;
use DateTimeImmutable;

/**
 * Unlike EloquentCartRepository, this never deletes-and-reinserts items:
 * Order items are immutable (Immutable Order Items rule), so save() only
 * ever inserts them once, the first time a brand-new Order is persisted.
 * Every later save() (a status change) touches only the `orders` row.
 */
class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?OrderEntity
    {
        $model = OrderModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function orderNumberExists(string $orderNumber, int $tenantId): bool
    {
        return OrderModel::query()
            ->where('tenant_id', $tenantId)
            ->where('order_number', $orderNumber)
            ->exists();
    }

    public function listByTenant(int $tenantId, ?OrderStatus $status, int $limit): array
    {
        $builder = OrderModel::query()->where('tenant_id', $tenantId);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        return $builder->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (OrderModel $model) => $this->toEntity($model))
            ->all();
    }

    public function listByCustomer(int $customerId, int $tenantId, int $limit): array
    {
        return OrderModel::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (OrderModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(OrderEntity $order): OrderEntity
    {
        $isNew = $order->id() === null;

        $model = $isNew
            ? new OrderModel()
            : OrderModel::query()->where('tenant_id', $order->tenantId())->findOrFail($order->id());

        $model->tenant_id = $order->tenantId();
        $model->agent_id = $order->agentId();
        $model->customer_id = $order->customerId();
        $model->order_number = $order->orderNumber()->value();
        $model->status = $order->status()->value;
        $model->subtotal_amount = $order->subtotal()->amount();
        $model->subtotal_currency = $order->subtotal()->currency();
        $model->tax_amount = $order->tax()->amount();
        $model->discount_amount = $order->discount()->amount();
        $model->total_amount = $order->total()->amount();
        $model->total_currency = $order->total()->currency();
        $model->notes = $order->notes();
        $model->save();

        if ($isNew) {
            foreach ($order->items() as $item) {
                $model->items()->create([
                    'product_id' => $item->productId(),
                    'quantity' => $item->quantity()->value(),
                    'unit_price_amount' => $item->unitPrice()->amount(),
                    'unit_price_currency' => $item->unitPrice()->currency(),
                    'total_price_amount' => $item->totalAmount(),
                    'total_price_currency' => $item->unitPrice()->currency(),
                ]);
            }
        }

        return $this->toEntity($model->fresh('items'));
    }

    private function toEntity(OrderModel $model): OrderEntity
    {
        $items = $model->items->map(fn (OrderItemModel $itemModel) => OrderItem::create(
            productId: $itemModel->product_id,
            quantity: new Quantity($itemModel->quantity),
            unitPrice: Money::fromAmount($itemModel->unit_price_amount, $itemModel->unit_price_currency),
        ))->all();

        return new OrderEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            agentId: $model->agent_id,
            customerId: $model->customer_id,
            orderNumber: new OrderNumber($model->order_number),
            status: OrderStatus::from($model->status),
            items: $items,
            subtotal: Money::fromAmount($model->subtotal_amount, $model->subtotal_currency),
            tax: Money::fromAmount($model->tax_amount, $model->subtotal_currency),
            discount: Money::fromAmount($model->discount_amount, $model->subtotal_currency),
            total: Money::fromAmount($model->total_amount, $model->total_currency),
            notes: $model->notes,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
