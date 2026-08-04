<?php

namespace App\Modules\Commerce\Domain\Entities;

use App\Modules\Commerce\Domain\ValueObjects\DiscountType;
use App\Modules\Commerce\Domain\ValueObjects\Money;

/**
 * One DiscountRule (or Coupon) currently active on a Cart — the
 * Cart-side mirror of the existing `Discount` entity's own Order-side
 * role (§7.5), the identical duality `CartItem`/`OrderItem` already
 * establish: a Cart's own state is mutable/re-computed on demand
 * (`EloquentAppliedDiscountRepository::replaceForCart()` deletes and
 * reinserts the whole set every time `ApplyDiscountsToCartAction` runs,
 * the same "small, frequently-mutated collection" shape
 * `EloquentCartRepository::save()`'s own item-replacement already has,
 * §7.2), while an Order's own `Discount` rows are written once and never
 * touched again. Deliberately scoped to Carts only this stage — no
 * `order_id` column at all, even though nothing stops one being added
 * later — the Order-side equivalent already exists as `Discount`
 * (widened with `discountRuleId` this stage, §7.24), and giving
 * `applied_discounts` an Order path too would recreate the exact
 * two-sources-of-truth problem this stage's own design note (§7.24)
 * describes avoiding.
 *
 * No `id`/`cartId` property on the Domain entity, the same shape
 * `CartItem` already has for the identical reason (a whole-collection
 * replace, never a single-row lookup by its own id).
 */
final class AppliedDiscount
{
    /**
     * @param list<int> $appliedToProductIds
     */
    public function __construct(
        private readonly ?int $discountRuleId,
        private readonly ?int $couponId,
        private readonly DiscountType $discountType,
        private readonly Money $discountAmount,
        private readonly array $appliedToProductIds,
    ) {
    }

    public function discountRuleId(): ?int
    {
        return $this->discountRuleId;
    }

    public function couponId(): ?int
    {
        return $this->couponId;
    }

    public function discountType(): DiscountType
    {
        return $this->discountType;
    }

    public function discountAmount(): Money
    {
        return $this->discountAmount;
    }

    /**
     * @return list<int>
     */
    public function appliedToProductIds(): array
    {
        return $this->appliedToProductIds;
    }
}
