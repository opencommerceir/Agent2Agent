<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\PricingData;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use App\Modules\Commerce\Domain\Entities\PaymentSession;
use App\Modules\Commerce\Domain\Repositories\PaymentSessionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;

/**
 * Starts a real, redirect-based charge (Zibal/Stripe/... — whichever
 * `RedirectPaymentGatewayInterface` is registered under `$gatewayName`,
 * §7.37): computes pricing by **composing `CalculatePricingAction`**
 * (Actions composing Actions, HANDOFF §3 pattern #3) rather than
 * re-deriving `resolveRuleDiscount()`/`buildEvaluationContext()` a third
 * time — this also means Cart ownership/non-empty validation comes free,
 * `CalculatePricingAction` already throws `CartNotFoundException`/
 * `InvalidArgumentException` for an invalid Cart, so this Action never
 * needs its own duplicate guard.
 *
 * Backs `commerce.payment.initiate`. The returned `tracking_reference`
 * is the new `PaymentSession`'s own local id — **never** a gateway's own
 * trackId/session id — so a caller never needs to know which gateway is
 * actually behind it (the whole point of this being gateway-agnostic).
 */
final class InitiatePaymentAction
{
    public function __construct(
        private readonly CalculatePricingAction $calculatePricing,
        private readonly PaymentGatewayRegistry $gateways,
        private readonly PaymentSessionRepositoryInterface $sessions,
    ) {
    }

    /**
     * @return array{redirect_url: string, tracking_reference: int, gateway: string}
     */
    public function execute(
        int $tenantId,
        int $agentId,
        int $cartId,
        ?string $gatewayName = null,
        ?string $couponCode = null,
        ?int $customerId = null,
        ?string $notes = null,
        ?string $region = null,
        ?string $mobile = null,
    ): array {
        /** @var PricingData $pricing */
        $pricing = $this->calculatePricing->execute($tenantId, $agentId, $cartId, $couponCode, $region);

        $gatewayName ??= (string) config('payment_gateways.default', 'mock');
        $gateway = $this->gateways->get($gatewayName);

        $total = Money::fromAmount($pricing->totalAmount, $pricing->totalCurrency);
        $tax = Money::fromAmount($pricing->taxAmount, $pricing->totalCurrency);
        $discount = Money::fromAmount($pricing->discountAmount, $pricing->totalCurrency);

        $session = PaymentSession::create(
            tenantId: $tenantId,
            cartId: $cartId,
            agentId: $agentId,
            gateway: $gatewayName,
            total: $total,
            tax: $tax,
            discount: $discount,
            couponCode: $couponCode,
            customerId: $customerId,
            notes: $notes,
            region: $region,
        );
        $session = $this->sessions->save($session);

        $callbackUrl = route('payments.callback', ['gateway' => $gatewayName, 'session' => $session->id()]);

        $result = $gateway->initiate($total, $callbackUrl, [
            'reference' => (string) $session->id(),
            'description' => $notes,
            'mobile' => $mobile,
        ]);

        $session->markInitiated($result->providerReference);
        $this->sessions->save($session);

        return [
            'redirect_url' => $result->redirectUrl,
            'tracking_reference' => $session->id(),
            'gateway' => $gatewayName,
        ];
    }
}
