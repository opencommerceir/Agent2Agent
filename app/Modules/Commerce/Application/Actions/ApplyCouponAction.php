<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Domain\Entities\Discount;
use App\Modules\Commerce\Domain\Events\CouponWasApplied;
use App\Modules\Commerce\Domain\Events\DiscountWasApplied;
use App\Modules\Commerce\Domain\Exceptions\InvalidCouponException;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\DiscountRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use Illuminate\Support\Facades\Event;

/**
 * The *durable* counterpart to CalculatePricingAction's preview: records
 * that a Coupon was actually used against a real, already-placed Order —
 * increments usedCount and writes the frozen Discount row. Only ever
 * called once a checkout has genuinely succeeded (ProcessPaymentAction),
 * never during a pricing preview, so merely previewing a total with a
 * coupon code can never consume one of its limited uses.
 */
final class ApplyCouponAction
{
    public function __construct(
        private readonly CouponRepositoryInterface $coupons,
        private readonly DiscountRepositoryInterface $discounts,
    ) {
    }

    public function execute(int $couponId, int $tenantId, int $orderId, Money $discountAmount): void
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
        );
        $discount = $this->discounts->save($discount);

        Event::dispatch(new DiscountWasApplied($discount));
    }
}
