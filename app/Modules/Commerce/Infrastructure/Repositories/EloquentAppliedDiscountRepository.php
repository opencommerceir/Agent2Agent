<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\AppliedDiscount as AppliedDiscountEntity;
use App\Modules\Commerce\Domain\Repositories\AppliedDiscountRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Infrastructure\Models\AppliedDiscount as AppliedDiscountModel;
use Illuminate\Support\Facades\DB;

class EloquentAppliedDiscountRepository implements AppliedDiscountRepositoryInterface
{
    public function listByCart(int $cartId, int $tenantId): array
    {
        return AppliedDiscountModel::query()
            ->where('tenant_id', $tenantId)
            ->where('cart_id', $cartId)
            ->get()
            ->map(fn (AppliedDiscountModel $model) => $this->toEntity($model))
            ->all();
    }

    public function replaceForCart(int $cartId, int $tenantId, array $discounts): void
    {
        DB::transaction(function () use ($cartId, $tenantId, $discounts) {
            AppliedDiscountModel::query()->where('tenant_id', $tenantId)->where('cart_id', $cartId)->delete();

            foreach ($discounts as $discount) {
                AppliedDiscountModel::query()->create([
                    'tenant_id' => $tenantId,
                    'cart_id' => $cartId,
                    'discount_rule_id' => $discount->discountRuleId(),
                    'coupon_id' => $discount->couponId(),
                    'discount_type' => $discount->discountType()->value,
                    'discount_amount' => $discount->discountAmount()->amount(),
                    'discount_currency' => $discount->discountAmount()->currency(),
                    'applied_to' => $discount->appliedToProductIds(),
                    'created_at' => now(),
                ]);
            }
        });
    }

    private function toEntity(AppliedDiscountModel $model): AppliedDiscountEntity
    {
        return new AppliedDiscountEntity(
            discountRuleId: $model->discount_rule_id,
            couponId: $model->coupon_id,
            discountType: DiscountType::from($model->discount_type),
            discountAmount: Money::fromAmount($model->discount_amount, $model->discount_currency),
            appliedToProductIds: $model->applied_to,
        );
    }
}
