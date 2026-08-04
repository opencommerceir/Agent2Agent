<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Domain\Entities\Discount;
use App\Modules\Commerce\Domain\Events\CouponWasApplied;
use App\Modules\Commerce\Domain\Events\DiscountRuleWasApplied;
use App\Modules\Commerce\Domain\Events\DiscountWasApplied;
use App\Modules\Commerce\Domain\Exceptions\InvalidCouponException;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\DiscountRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use Illuminate\Support\Facades\Event;

/**
 * The *durable* counterpart to CalculatePricingAction's preview: records
 * that a Coupon was actually used against a real, already-placed Order —
 * increments usedCount and writes the frozen Discount row. Only ever
 * called once a checkout has genuinely succeeded (ProcessPaymentAction),
 * never during a pricing preview, so merely previewing a total with a
 * coupon code can never consume one of its limited uses.
 *
 * discountRuleId (Phase 5, Stage 4, §7.24) mirrors that same "durable
 * only" rule onto the linked DiscountRule's own usedCount: when the
 * redeemed Coupon was rule-linked, this method also records the rule's
 * usage and dispatches DiscountRuleWasApplied — the Coupon's own
 * usedCount/Discount row are still recorded regardless (they answer "was
 * this Coupon used", not "was this Rule used"). An orphaned link (rule
 * deleted between pricing preview and checkout) is skipped silently
 * rather than failing an otherwise-successful, already-charged checkout.
 */
final class ApplyCouponAction
{
    public function __construct(
        private readonly CouponRepositoryInterface $coupons,
        private readonly DiscountRepositoryInterface $discounts,
        private readonly DiscountRuleRepositoryInterface $discountRules,
    ) {
    }

    public function execute(int $couponId, int $tenantId, int $orderId, Money $discountAmount, ?int $discountRuleId = null): void
    {
        $coupon = $this->coupons->findById($couponId, $tenantId);

        if (! $coupon) {
            throw new InvalidCouponException("Coupon [{$couponId}] does not exist.");
        }

        $coupon->recordUsage();
        $coupon = $this->coupons->save($coupon);

        Event::dispatch(new CouponWasApplied($coupon, $orderId));

        $discount = Discount::apply(
            orderId: $orderId,
            couponId: $coupon->id(),
            type: $coupon->discountType(),
            amount: $discountAmount,
            description: "Coupon {$coupon->code()} applied.",
            discountRuleId: $discountRuleId,
        );
        $discount = $this->discounts->save($discount);

        Event::dispatch(new DiscountWasApplied($discount));

        if ($discountRuleId !== null) {
            $rule = $this->discountRules->findById($discountRuleId, $tenantId);

            if ($rule) {
                $rule->recordUsage();
                $rule = $this->discountRules->save($rule);

                Event::dispatch(new DiscountRuleWasApplied($rule));
            }
        }
    }
}
