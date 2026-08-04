<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\PricingData;
use App\Modules\Commerce\Domain\Entities\Cart;
use App\Modules\Commerce\Domain\Entities\CartItem;
use App\Modules\Commerce\Domain\Entities\Coupon;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\InvalidCouponException;
use App\Modules\Commerce\Application\Services\TaxRateProviderInterface;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\Services\CouponValidationService;
use App\Modules\Commerce\Domain\Services\DiscountCalculator;
use App\Modules\Commerce\Domain\Services\PricingService;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use App\Modules\Commerce\Domain\ValueObjects\DiscountEvaluationContext;
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
 * DEFAULT_TAX_RATE_PERCENT is now only the last-resort fallback, not the
 * only rate: TaxRateProviderInterface is asked first (Phase 3.2 — Finance
 * module), and its answer is used whenever it isn't null. A Commerce
 * deployment with no Finance module installed gets NullTaxRateProvider
 * bound by default (CommerceServiceProvider::register()), which always
 * returns null — so this constant, and this Action's behavior for every
 * existing caller that doesn't pass a `$region`, is completely unchanged
 * from before Finance existed. See TaxRateProviderInterface's own
 * docblock for the full reasoning behind this being an Interface Commerce
 * owns, not a direct dependency on Finance's TaxCalculationService.
 */
final class CalculatePricingAction
{
    private const DEFAULT_TAX_RATE_PERCENT = 9.0;

    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly CouponRepositoryInterface $coupons,
        private readonly CouponValidationService $couponValidation,
        private readonly PricingService $pricingService,
        private readonly TaxRateProviderInterface $taxRateProvider,
        private readonly DiscountRuleRepositoryInterface $discountRules,
        private readonly DiscountCalculator $discountCalculator,
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function execute(int $tenantId, int $agentId, int $cartId, ?string $couponCode = null, ?string $region = null): PricingData
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
            $discount = $coupon->discountRuleId() !== null
                ? $this->resolveRuleDiscount($coupon, $subtotal, $cart, $tenantId)
                : $coupon->calculateDiscount($subtotal);
        }

        $ratePercent = $this->taxRateProvider->getRatePercent($tenantId, $region) ?? self::DEFAULT_TAX_RATE_PERCENT;

        $breakdown = $this->pricingService->calculate($subtotal, new TaxRate($ratePercent), $discount);

        return PricingData::fromBreakdown($breakdown);
    }

    /**
     * The DiscountRule bypass (Phase 5, Stage 4, §7.24): a Coupon linked
     * to a rule defers to DiscountCalculator instead of its own
     * calculateDiscount(). An orphaned link (rule deleted after the
     * Coupon was created) falls back to the Coupon's own calculation
     * rather than failing a mere preview — see Coupon's own docblock for
     * why discountType/discountValue stay populated even on a
     * rule-linked Coupon.
     */
    private function resolveRuleDiscount(Coupon $coupon, Money $subtotal, Cart $cart, int $tenantId): Money
    {
        $rule = $this->discountRules->findById($coupon->discountRuleId(), $tenantId);

        if (! $rule) {
            return $coupon->calculateDiscount($subtotal);
        }

        return $this->discountCalculator->calculate($rule, $this->buildEvaluationContext($cart, $subtotal, $tenantId));
    }

    /**
     * Builds the Domain Service's own input from a real Cart — per
     * DiscountEvaluationContext's own docblock, neither Domain Service may
     * query a Repository, so the bounded per-item Product lookup for
     * categoryId happens here, in the Action.
     */
    private function buildEvaluationContext(Cart $cart, Money $subtotal, int $tenantId): DiscountEvaluationContext
    {
        $items = array_map(
            fn (CartItem $item) => [
                'productId' => $item->productId(),
                'categoryId' => $this->products->findById($item->productId(), $tenantId)?->categoryId(),
                'quantity' => $item->quantity()->value(),
                'unitPriceAmount' => $item->unitPrice()->amount(),
            ],
            $cart->items(),
        );

        return new DiscountEvaluationContext(
            subtotalAmount: $subtotal->amount(),
            currency: $subtotal->currency(),
            items: $items,
        );
    }
}
