<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Application\DTOs\PaymentData;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Application\Services\TaxRateProviderInterface;
use App\Modules\Commerce\Domain\Entities\CartItem;
use App\Modules\Commerce\Domain\Entities\Payment;
use App\Modules\Commerce\Domain\Events\PaymentWasProcessed;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\InvalidCouponException;
use App\Modules\Commerce\Domain\Exceptions\PaymentFailedException;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\PaymentRepositoryInterface;
use App\Modules\Commerce\Domain\Services\CouponValidationService;
use App\Modules\Commerce\Domain\Services\PricingService;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use App\Modules\Commerce\Domain\ValueObjects\PaymentStatus;
use App\Modules\Commerce\Domain\ValueObjects\TaxRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
 * "Transaction Safety" rule. Note this only stays correct because
 * MockPaymentGateway is synchronous and local — a real gateway
 * integration should charge *outside* the transaction and only wrap the
 * subsequent DB writes, so a slow network call never holds a DB lock;
 * that boundary change is deliberately out of scope until a real
 * gateway exists.
 *
 * ApplyCouponAction (incrementing usedCount, writing the Discount row)
 * only runs after the Order has been placed — never during pricing —
 * so a coupon's limited uses are only ever consumed by a checkout that
 * actually completed.
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
        private readonly PaymentRepositoryInterface $payments,
        private readonly CouponValidationService $couponValidation,
        private readonly PricingService $pricingService,
        private readonly PaymentGatewayInterface $gateway,
        private readonly PlaceOrderAction $placeOrder,
        private readonly ApplyCouponAction $applyCoupon,
        private readonly TaxRateProviderInterface $taxRateProvider,
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
                $discount = $coupon->calculateDiscount($subtotal);
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

            $order = $this->placeOrder->execute(
                tenantId: $tenantId,
                agentId: $agentId,
                cartId: $cartId,
                notes: $notes,
                customerId: $customerId,
                tax: $pricing->tax,
                discount: $pricing->discount,
                total: $pricing->total,
            );

            $payment = Payment::record(
                tenantId: $tenantId,
                orderId: $order->id,
                amount: $pricing->total,
                method: $method,
                status: PaymentStatus::Completed,
                transactionId: $result->transactionId,
                gatewayResponse: $result->rawResponse,
            );
            $payment = $this->payments->save($payment);

            Event::dispatch(new PaymentWasProcessed($payment));

            if ($coupon !== null) {
                $this->applyCoupon->execute($coupon->id(), $tenantId, $order->id, $pricing->discount);
            }

            return [
                'order' => $order,
                'payment' => PaymentData::fromEntity($payment),
            ];
        });
    }
}
