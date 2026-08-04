<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\AppliedDiscount;

final class AppliedDiscountData
{
    /**
     * @param list<int> $appliedToProductIds
     */
    public function __construct(
        public readonly ?int $discountRuleId,
        public readonly ?int $couponId,
        public readonly string $discountType,
        public readonly int $discountAmount,
        public readonly string $discountCurrency,
        public readonly array $appliedToProductIds,
    ) {
    }

    public static function fromEntity(AppliedDiscount $discount): self
    {
        return new self(
            discountRuleId: $discount->discountRuleId(),
            couponId: $discount->couponId(),
            discountType: $discount->discountType()->value,
            discountAmount: $discount->discountAmount()->amount(),
            discountCurrency: $discount->discountAmount()->currency(),
            appliedToProductIds: $discount->appliedToProductIds(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'discountRuleId' => $this->discountRuleId,
            'couponId' => $this->couponId,
            'discountType' => $this->discountType,
            'discountAmount' => $this->discountAmount,
            'discountCurrency' => $this->discountCurrency,
            'appliedToProductIds' => $this->appliedToProductIds,
        ];
    }
}
