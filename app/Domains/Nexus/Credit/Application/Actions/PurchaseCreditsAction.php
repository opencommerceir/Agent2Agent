<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Domain\Entities\CreditPurchaseSession;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditPurchaseSessionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPackage;
use App\Domains\Nexus\Credit\Domain\ValueObjects\Money;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use App\Modules\Commerce\Domain\ValueObjects\Money as GatewayMoney;
use InvalidArgumentException;

/**
 * Starts a real, redirect-based charge for a fixed credit package —
 * reuses Commerce's own ZibalPaymentGateway/StripePaymentGateway adapters
 * and PaymentGatewayRegistry (CLAUDE.md "Extend, Don't Rebuild"; Commerce's
 * own ServiceProvider is disabled since Nexus Phase 0, so Nexus registers
 * these same adapter classes under its own PaymentGatewayRegistry
 * singleton rather than reusing Commerce's, which is never booted).
 *
 * `RedirectPaymentGatewayInterface::initiate()` is typed against Commerce's
 * own Money VO, not Credit's — a narrow, deliberate crossing of the "each
 * domain owns its Money VO" rule at exactly this one Infrastructure-facing
 * call site, since duplicating real Zibal/Stripe HTTP integration to avoid
 * it would itself violate "never rebuild." Credit's own Money never leaves
 * this Action.
 *
 * **Zibal only, for now.** Packages are priced in Toman (IRT) —
 * ZibalPaymentGateway itself demands IRR, so the amount is converted
 * (×10) at this exact boundary, nowhere else. Stripe is registered in the
 * gateway registry (proving the connector wiring works) but every
 * Stripe-priced flow would need its own non-IRT package set Stripe can
 * actually charge (Stripe does not support IRR/IRT at all) — that's a
 * real, separate feature this phase's 3 Toman-only packages don't cover,
 * not a bug.
 */
final class PurchaseCreditsAction
{
    private const ZIBAL_RIAL_PER_TOMAN = 10;

    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly CreditPurchaseSessionRepositoryInterface $sessions,
    ) {
    }

    /**
     * @return array{redirect_url: string, tracking_reference: int}
     */
    public function execute(int $businessId, CreditPackage $package, string $gatewayName = 'zibal'): array
    {
        if ($gatewayName !== 'zibal') {
            throw new InvalidArgumentException(
                "Gateway [{$gatewayName}] is not yet supported for Toman-priced credit packages — only 'zibal' is."
            );
        }

        $gateway = $this->gateways->get($gatewayName);
        $total = Money::fromAmount($package->priceAmountToman(), 'IRT');

        $session = CreditPurchaseSession::create($businessId, $gatewayName, $package, $total);
        $session = $this->sessions->save($session);

        $callbackUrl = route('nexus.credit.purchase.callback', ['gateway' => $gatewayName, 'session' => $session->id()]);

        $gatewayAmount = GatewayMoney::fromAmount($total->amount() * self::ZIBAL_RIAL_PER_TOMAN, 'IRR');
        $result = $gateway->initiate($gatewayAmount, $callbackUrl, [
            'reference' => (string) $session->id(),
            'description' => "Nexus credit package: {$package->value}",
        ]);

        $session->markInitiated($result->providerReference);
        $this->sessions->save($session);

        return [
            'redirect_url' => $result->redirectUrl,
            'tracking_reference' => $session->id(),
        ];
    }
}
