<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\Coupon;

final class CouponData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $code,
        public readonly string $discountType,
        public readonly int $discountValue,
        public readonly ?int $minOrderAmount,
        public readonly ?int $maxUses,
        public readonly int $usedCount,
        public readonly ?string $expiresAt,
        public readonly bool $isActive,
        public readonly ?int $discountRuleId = null,
    ) {
    }

    public static function fromEntity(Coupon $coupon): self
    {
        return new self(
            id: $coupon->id(),
            tenantId: $coupon->tenantId(),
            code: $coupon->code()->value(),
            discountType: $coupon->discountType()->value,
            discountValue: $coupon->discountValue(),
            minOrderAmount: $coupon->minOrderAmount(),
            maxUses: $coupon->maxUses(),
            usedCount: $coupon->usedCount(),
            expiresAt: $coupon->expiresAt()?->format(DATE_ATOM),
            isActive: $coupon->isActive(),
            discountRuleId: $coupon->discountRuleId(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'code' => $this->code,
            'discountType' => $this->discountType,
            'discountValue' => $this->discountValue,
            'minOrderAmount' => $this->minOrderAmount,
            'maxUses' => $this->maxUses,
            'usedCount' => $this->usedCount,
            'expiresAt' => $this->expiresAt,
            'isActive' => $this->isActive,
            'discountRuleId' => $this->discountRuleId,
        ];
    }
}
