<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Application\DTOs\PaymentData;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use App\Modules\Commerce\Domain\Entities\PaymentSession;
use App\Modules\Commerce\Domain\Exceptions\PaymentSessionNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\PaymentRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\PaymentSessionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;

/**
 * Confirms a redirect-based charge server-to-server — **never** trusts a
 * caller-supplied success flag (a callback query string, a webhook
 * payload), always re-asks the gateway itself via `verify()`, exactly
 * matching both Zibal's and Stripe's own explicit documentation (§7.37).
 * Backs `commerce.payment.confirm` *and* both public callback/webhook
 * routes (`routes/payments.php`) — the same Action, two different
 * callers, distinguished only by whether a real, authenticated
 * `$tenantId` is available:
 *
 * - MCP/Agent-facing (`$tenantId` given): a tenant-scoped
 *   `PaymentSession` lookup — a cross-tenant id throws
 *   `PaymentSessionNotFoundException` (404), the same shape every other
 *   tenant-scoped `findById()` in this codebase already has.
 * - Public callback/webhook (`$tenantId` null): looks up by id alone
 *   (`findByIdUnscoped()`) — safe only because this Action's own
 *   `verify()` call is what actually decides success, never anything
 *   the caller claims (see that repository method's own docblock).
 *
 * **Idempotent**: an already-`Completed` session returns its existing
 * Order/Payment again rather than re-running
 * `FinalizeSuccessfulPaymentAction` a second time — required for
 * webhook retries (Stripe's own docs: the same event may be delivered
 * more than once) and for a gateway's callback firing on top of an
 * already-processed webhook (or vice versa).
 */
final class ConfirmRedirectPaymentAction
{
    public function __construct(
        private readonly PaymentSessionRepositoryInterface $sessions,
        private readonly PaymentGatewayRegistry $gateways,
        private readonly CouponRepositoryInterface $coupons,
        private readonly OrderRepositoryInterface $orders,
        private readonly PaymentRepositoryInterface $payments,
        private readonly FinalizeSuccessfulPaymentAction $finalizePayment,
    ) {
    }

    /**
     * @return array{successful: bool, order: ?OrderData, payment: ?PaymentData, message: string}
     */
    public function execute(int $sessionId, ?int $tenantId = null): array
    {
        $session = $tenantId !== null
            ? $this->sessions->findById($sessionId, $tenantId)
            : $this->sessions->findByIdUnscoped($sessionId);

        if (! $session) {
            throw new PaymentSessionNotFoundException("Payment session [{$sessionId}] does not exist.");
        }

        if ($session->isCompleted()) {
            return $this->alreadyCompletedResult($session);
        }

        $gateway = $this->gateways->get($session->gateway());
        $result = $gateway->verify((string) $session->providerReference());

        if (! $result->successful) {
            if ($session->isPending()) {
                $session->fail();
                $this->sessions->save($session);
            }

            return [
                'successful' => false,
                'order' => null,
                'payment' => null,
                'message' => 'Payment was not successful: '.($result->rawResponse['message'] ?? 'no reason given by the gateway.'),
            ];
        }

        $coupon = $session->couponCode() !== null
            ? $this->coupons->findByCode(new CouponCode($session->couponCode()), $session->tenantId())
            : null;

        // Redirect gateways don't report back a finer-grained payment
        // method than "the buyer paid on the gateway's own hosted page" —
        // CreditCard is the honest default both Zibal (bank card) and
        // Stripe Checkout (predominantly card) actually mean here.
        $finalized = $this->finalizePayment->execute(
            tenantId: $session->tenantId(),
            agentId: $session->agentId(),
            cartId: $session->cartId(),
            tax: $session->tax(),
            discount: $session->discount(),
            total: $session->total(),
            method: PaymentMethod::CreditCard,
            gateway: $session->gateway(),
            transactionId: $result->transactionId,
            gatewayResponse: $result->rawResponse,
            notes: $session->notes(),
            customerId: $session->customerId(),
            coupon: $coupon,
        );

        $session->complete($finalized['order']->id);
        $this->sessions->save($session);

        return [
            'successful' => true,
            'order' => $finalized['order'],
            'payment' => $finalized['payment'],
            'message' => 'Payment confirmed.',
        ];
    }

    /**
     * @return array{successful: bool, order: ?OrderData, payment: ?PaymentData, message: string}
     */
    private function alreadyCompletedResult(PaymentSession $session): array
    {
        $order = $session->orderId() !== null
            ? $this->orders->findById($session->orderId(), $session->tenantId())
            : null;
        $payment = $order ? $this->payments->findByOrderId($order->id(), $session->tenantId()) : null;

        return [
            'successful' => true,
            'order' => $order ? OrderData::fromEntity($order) : null,
            'payment' => $payment ? PaymentData::fromEntity($payment) : null,
            'message' => 'Payment already confirmed.',
        ];
    }
}
