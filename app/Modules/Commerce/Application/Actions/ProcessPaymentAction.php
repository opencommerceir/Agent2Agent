<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Application\DTOs\PaymentData;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Application\Services\TaxRateProviderInterface;
use App\Modules\Commerce\Domain\Entities\Cart;
use App\Modules\Commerce\Domain\Entities\CartItem;
use App\Modules\Commerce\Domain\Entities\Coupon;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\InvalidCouponException;
use App\Modules\Commerce\Domain\Exceptions\PaymentFailedException;
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
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use App\Modules\Commerce\Domain\ValueObjects\TaxRate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The full "Cart → Payment → Order confirmation" checkout flow, in the
 * exact sequence this stage specified: fetch Cart, compute pricing,
 * validate/price a Coupon if given, charge the Payment Gateway, and only
 * *then* — only if the charge succeeded — place the Order and persist a
 * Payment record. This ordering is why Payment.order_id is never
 * nullable: a failed charge simply never reaches the point where an
 * Order (or a Payment row) exists at all (Payment entity's own
 * docblock).
 *
 * DB::transaction wraps the whole thing per this stage's explicit
 * "Transaction Safety" rule — still correct because MockPaymentGateway
 * (the only `PaymentGatewayInterface` implementation) is synchronous and
 * local. The real, redirect-based gateways added in §7.37
 * (Zibal/Stripe) don't use this synchronous `charge()` contract at all
 * — see `RedirectPaymentGatewayInterface`/`InitiatePaymentAction`/
 * `ConfirmRedirectPaymentAction` for that separate, async flow, which
 * *does* charge outside any DB transaction (the real fix HANDOFF §8.10
 * asked for, reached via `FinalizeSuccessfulPaymentAction`'s own
 * transaction boundary rather than changed here).
 *
 * The Order-placement/Payment-record/coupon-apply tail is extracted into
 * `FinalizeSuccessfulPaymentAction` (§7.37) — composed here unchanged in
 * observable behavior, so it can be shared with
 * `ConfirmRedirectPaymentAction` without duplicating this
 * security/money-relevant sequence a second time.
 *
 * DEFAULT_TAX_RATE_PERCENT is now only the last-resort fallback — see
 * CalculatePricingAction's docblock for the full reasoning (both Actions
 * gained the identical TaxRateProviderInterface dependency together).
 */
final class ProcessPaymentAction
{
    private const DEFAULT_TAX_RATE_PERCENT = 9.0;

    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly CouponRepositoryInterface $coupons,
        private readonly CouponValidationService $couponValidation,
        private readonly PricingService $pricingService,
        private readonly PaymentGatewayInterface $gateway,
        private readonly FinalizeSuccessfulPaymentAction $finalizePayment,
        private readonly TaxRateProviderInterface $taxRateProvider,
        private readonly DiscountRuleRepositoryInterface $discountRules,
        private readonly DiscountCalculator $discountCalculator,
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    /**
     * @param array<string, mixed> $paymentDetails
     * @return array{order: OrderData, payment: PaymentData}
     */
    public function execute(
        int $tenantId,
        int $agentId,
        int $cartId,
        string $paymentMethod,
        array $paymentDetails,
        ?string $couponCode = null,
        ?string $notes = null,
        ?int $customerId = null,
        ?string $region = null,
    ): array {
        return DB::transaction(function () use (
            $tenantId, $agentId, $cartId, $paymentMethod, $paymentDetails, $couponCode, $notes, $customerId, $region,
        ) {
            $cart = $this->carts->findById($cartId, $tenantId);

            if (! $cart || $cart->ownerType() !== MemberType::Agent || $cart->ownerId() !== $agentId) {
                throw new CartNotFoundException("Cart [{$cartId}] does not exist.");
            }

            if (! $cart->isActive() || $cart->items() === []) {
                throw new InvalidArgumentException('Cart is empty or not active.');
            }

            $currency = $cart->items()[0]->unitPrice()->currency();
            $subtotalAmount = array_sum(array_map(fn (CartItem $item) => $item->subtotalAmount(), $cart->items()));
            $subtotal = Money::fromAmount($subtotalAmount, $currency);

            $coupon = null;
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

            $pricing = $this->pricingService->calculate($subtotal, new TaxRate($ratePercent), $discount);

            $method = PaymentMethod::from($paymentMethod);
            $result = $this->gateway->charge($pricing->total, $method, $paymentDetails);

            if (! $result->successful) {
                throw new PaymentFailedException(
                    'Payment was declined: '.($result->rawResponse['message'] ?? 'no reason given by the gateway.')
                );
            }

            return $this->finalizePayment->execute(
                tenantId: $tenantId,
                agentId: $agentId,
                cartId: $cartId,
                tax: $pricing->tax,
                discount: $pricing->discount,
                total: $pricing->total,
                method: $method,
                gateway: 'mock',
                transactionId: $result->transactionId,
                gatewayResponse: $result->rawResponse,
                notes: $notes,
                customerId: $customerId,
                coupon: $coupon,
            );
        });
    }

    /**
     * The DiscountRule bypass (Phase 5, Stage 4, §7.24) — identical to
     * CalculatePricingAction's own resolveRuleDiscount()/buildEvaluationContext();
     * duplicated rather than shared because the two Actions have no common
     * base class and each is already self-contained (PlaceOrderAction's own
     * precedent: small, Action-local helpers over a shared trait for logic
     * this narrow).
     */
    private function resolveRuleDiscount(Coupon $coupon, Money $subtotal, Cart $cart, int $tenantId): Money
    {
        $rule = $this->discountRules->findById($coupon->discountRuleId(), $tenantId);

        if (! $rule) {
            return $coupon->calculateDiscount($subtotal);
        }

        return $this->discountCalculator->calculate($rule, $this->buildEvaluationContext($cart, $subtotal, $tenantId));
    }

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
