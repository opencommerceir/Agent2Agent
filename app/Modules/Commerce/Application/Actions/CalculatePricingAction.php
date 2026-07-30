<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\PricingData;
use App\Modules\Commerce\Domain\Entities\CartItem;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\InvalidCouponException;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\Services\CouponValidationService;
use App\Modules\Commerce\Domain\Services\PricingService;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\TaxRate;
use InvalidArgumentException;

/**
 * A pure preview: computes what checkout *would* cost without applying
 * anything durable — it never increments a Coupon's usedCount and never
 * creates a Discount row (ApplyCouponAction is the durable counterpart,
 * called only once payment actually succeeds). Backs
 * `commerce.checkout.calculate`.
 *
 * DEFAULT_TAX_RATE is a stand-in: no per-tenant tax-rate configuration
 * entity exists yet (this stage didn't request one), so every tenant
 * currently gets the same flat rate. A future stage adding real tax
 * configuration would replace this constant with a repository lookup —
 * ProcessPaymentAction carries the identical constant for the same
 * reason, since both need it and no shared config source exists to pull
 * it from yet.
 */
final class CalculatePricingAction
{
    private const DEFAULT_TAX_RATE_PERCENT = 9.0;

    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly CouponRepositoryInterface $coupons,
        private readonly CouponValidationService $couponValidation,
        private readonly PricingService $pricingService,
    ) {
    }

    public function execute(int $tenantId, int $agentId, int $cartId, ?string $couponCode = null): PricingData
    {
        $cart = $this->carts->findById($cartId, $tenantId);

        if (! $cart || $cart->ownerType() !== MemberType::Agent || $cart->ownerId() !== $agentId) {
            throw new CartNotFoundException("Cart [{$cartId}] does not exist.");
        }

        if ($cart->items() === []) {
            throw new InvalidArgumentException('Cart is empty.');
        }

        $currency = $cart->items()[0]->unitPrice()->currency();
        $subtotalAmount = array_sum(array_map(fn (CartItem $item) => $item->subtotalAmount(), $cart->items()));
        $subtotal = Money::fromAmount($subtotalAmount, $currency);

        $discount = Money::fromAmount(0, $currency);

        if ($couponCode !== null) {
            $coupon = $this->coupons->findByCode(new CouponCode($couponCode), $tenantId);

            if (! $coupon) {
                throw new InvalidCouponException("Coupon [{$couponCode}] does not exist.");
            }

            $this->couponValidation->validate($coupon, $subtotal);
            $discount = $coupon->calculateDiscount($subtotal);
        }

        $breakdown = $this->pricingService->calculate($subtotal, new TaxRate(self::DEFAULT_TAX_RATE_PERCENT), $discount);

        return PricingData::fromBreakdown($breakdown);
    }
}
