<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Discount as DiscountEntity;
use App\Modules\Commerce\Domain\Repositories\DiscountRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Infrastructure\Models\Discount as DiscountModel;

class EloquentDiscountRepository implements DiscountRepositoryInterface
{
    public function save(DiscountEntity $discount): DiscountEntity
    {
        $model = new DiscountModel();
        $model->order_id = $discount->orderId();
        $model->coupon_id = $discount->couponId();
        $model->discount_type = $discount->type()->value;
        $model->discount_amount = $discount->amount()->amount();
        $model->discount_currency = $discount->amount()->currency();
        $model->description = $discount->description();
        $model->save();

        return $this->toEntity($model);
    }

    public function listByOrder(int $orderId): array
    {
        return DiscountModel::query()
            ->where('order_id', $orderId)
            ->get()
            ->map(fn (DiscountModel $model) => $this->toEntity($model))
            ->all();
    }

    private function toEntity(DiscountModel $model): DiscountEntity
    {
        return DiscountEntity::apply(
            orderId: $model->order_id,
            couponId: $model->coupon_id,
            type: DiscountType::from($model->discount_type),
            amount: Money::fromAmount($model->discount_amount, $model->discount_currency),
            description: $model->description,
        );
    }
}
