<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Application\DTOs\PaymentData;
use App\Modules\Commerce\Domain\Entities\Coupon;
use App\Modules\Commerce\Domain\Entities\Payment;
use App\Modules\Commerce\Domain\Events\PaymentWasProcessed;
use App\Modules\Commerce\Domain\Repositories\PaymentRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use App\Modules\Commerce\Domain\ValueObjects\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * The common "a charge is now confirmed successful" tail — place the
 * Order, record the Payment, dispatch PaymentWasProcessed, and apply a
 * Coupon if one was used. Extracted from `ProcessPaymentAction`'s own
 * previously-inline logic (§7.37) so this security/money-relevant
 * sequence exists in exactly one place, composed by **both**
 * `ProcessPaymentAction` (the synchronous Mock-gateway path, refactored
 * to call this — behavior identical to before the extraction) and
 * `ConfirmRedirectPaymentAction` (the new async Zibal/Stripe path, once
 * a gateway's own `verify()` call confirms success server-side).
 *
 * Never decides *whether* a charge succeeded — that's each caller's own
 * job (a synchronous `PaymentGatewayResult` for `ProcessPaymentAction`,
 * a server-verified redirect-gateway result for
 * `ConfirmRedirectPaymentAction`); this Action only ever runs once that
 * decision has already been made.
 *
 * Wraps its own `DB::transaction()` (nests safely via Laravel's own
 * savepoint support inside `ProcessPaymentAction`'s own, wider,
 * pre-existing transaction — zero behavior change there) specifically so
 * `ConfirmRedirectPaymentAction` — which has no outer transaction of its
 * own, since the real gateway `verify()` network call it makes *before*
 * reaching here must never hold a DB lock — still gets exactly the same
 * atomic "Order + Payment + Coupon apply, all or nothing" guarantee
 * `ProcessPaymentAction` already had. This is the real fix HANDOFF
 * §8.10 already named ("a real gateway should charge outside the
 * transaction and only wrap the subsequent DB writes"), reached
 * naturally by this extraction rather than as a separate change.
 */
final class FinalizeSuccessfulPaymentAction
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly PlaceOrderAction $placeOrder,
        private readonly ApplyCouponAction $applyCoupon,
    ) {
    }

    /**
     * @param array<string, mixed> $gatewayResponse
     * @return array{order: OrderData, payment: PaymentData}
     */
    public function execute(
        int $tenantId,
        int $agentId,
        int $cartId,
        Money $tax,
        Money $discount,
        Money $total,
        PaymentMethod $method,
        ?string $gateway,
        ?string $transactionId,
        array $gatewayResponse,
        ?string $notes,
        ?int $customerId,
        ?Coupon $coupon,
    ): array {
        return DB::transaction(function () use (
            $tenantId, $agentId, $cartId, $tax, $discount, $total, $method, $gateway, $transactionId,
            $gatewayResponse, $notes, $customerId, $coupon,
        ) {
            $order = $this->placeOrder->execute(
                tenantId: $tenantId,
                agentId: $agentId,
                cartId: $cartId,
                notes: $notes,
                customerId: $customerId,
                tax: $tax,
                discount: $discount,
                total: $total,
            );

            $payment = Payment::record(
                tenantId: $tenantId,
                orderId: $order->id,
                amount: $total,
                method: $method,
                status: PaymentStatus::Completed,
                transactionId: $transactionId,
                gatewayResponse: $gatewayResponse,
                gateway: $gateway,
            );
            $payment = $this->payments->save($payment);

            Event::dispatch(new PaymentWasProcessed($payment));

            if ($coupon !== null) {
                $this->applyCoupon->execute($coupon->id(), $tenantId, $order->id, $discount, $coupon->discountRuleId());
            }

            return [
                'order' => $order,
                'payment' => PaymentData::fromEntity($payment),
            ];
        });
    }
}
