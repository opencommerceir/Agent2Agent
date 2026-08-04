<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Coupon as CouponEntity;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Infrastructure\Models\Coupon as CouponModel;
use DateTimeImmutable;

class EloquentCouponRepository implements CouponRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?CouponEntity
    {
        $model = CouponModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByCode(CouponCode $code, int $tenantId): ?CouponEntity
    {
        $model = CouponModel::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code->value())
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function codeExists(CouponCode $code, int $tenantId): bool
    {
        return CouponModel::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code->value())
            ->exists();
    }

    public function save(CouponEntity $coupon): CouponEntity
    {
        $model = $coupon->id()
            ? CouponModel::query()->where('tenant_id', $coupon->tenantId())->findOrFail($coupon->id())
            : new CouponModel();

        $model->tenant_id = $coupon->tenantId();
        $model->code = $coupon->code()->value();
        $model->discount_type = $coupon->discountType()->value;
        $model->discount_value = $coupon->discountValue();
        $model->min_order_amount = $coupon->minOrderAmount();
        $model->max_uses = $coupon->maxUses();
        $model->used_count = $coupon->usedCount();
        $model->expires_at = $coupon->expiresAt();
        $model->is_active = $coupon->isActive();
        $model->discount_rule_id = $coupon->discountRuleId();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(CouponModel $model): CouponEntity
    {
        return new CouponEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            code: new CouponCode($model->code),
            discountType: DiscountType::from($model->discount_type),
            discountValue: $model->discount_value,
            minOrderAmount: $model->min_order_amount,
            maxUses: $model->max_uses,
            usedCount: $model->used_count,
            expiresAt: $model->expires_at ? DateTimeImmutable::createFromInterface($model->expires_at) : null,
            isActive: $model->is_active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            discountRuleId: $model->discount_rule_id,
        );
    }
}
