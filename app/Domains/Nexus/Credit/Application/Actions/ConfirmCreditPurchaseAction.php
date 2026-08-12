<?php

namespace App\Domains\Nexus\Credit\Application\Actions;

use App\Domains\Nexus\Credit\Domain\Repositories\CreditPurchaseSessionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;

/**
 * Confirms a redirect-based credit purchase server-to-server — mirrors
 * Commerce's own ConfirmRedirectPaymentAction exactly: **never** trusts a
 * caller-supplied success flag (the callback's own query string), always
 * re-asks the gateway itself via verify(). Backs the public
 * `nexus/credit/payments/{gateway}/callback` route — no authenticated
 * Business session exists there, so `$businessId` is null and the lookup
 * is unscoped; safe because verify() is what actually decides success,
 * never anything the caller merely claims.
 *
 * **Idempotent**: an already-Completed session returns success again
 * rather than re-granting credits a second time — required for a gateway
 * redirecting the buyer back more than once (a page refresh, a double
 * click on "back").
 */
final class ConfirmCreditPurchaseAction
{
    public function __construct(
        private readonly CreditPurchaseSessionRepositoryInterface $sessions,
        private readonly PaymentGatewayRegistry $gateways,
        private readonly GrantCreditsAction $grantCredits,
    ) {
    }

    /**
     * @return array{successful: bool, creditsGranted: int, message: string}
     */
    public function execute(int $sessionId, ?int $businessId = null): array
    {
        $session = $businessId !== null
            ? $this->sessions->findById($sessionId, $businessId)
            : $this->sessions->findByIdUnscoped($sessionId);

        if (! $session) {
            return ['successful' => false, 'creditsGranted' => 0, 'message' => 'Credit purchase session not found.'];
        }

        if ($session->isCompleted()) {
            return ['successful' => true, 'creditsGranted' => $session->package()->creditsGranted(), 'message' => 'Purchase already confirmed.'];
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
                'creditsGranted' => 0,
                'message' => 'Payment was not successful: '.($result->rawResponse['message'] ?? 'no reason given by the gateway.'),
            ];
        }

        $this->grantCredits->execute(
            businessId: $session->businessId(),
            amount: $session->package()->creditsGranted(),
            type: CreditTransactionType::Purchase,
            reason: 'credit.purchase.'.$session->package()->value,
            relatedId: $session->id(),
        );

        $session->complete();
        $this->sessions->save($session);

        return [
            'successful' => true,
            'creditsGranted' => $session->package()->creditsGranted(),
            'message' => 'Payment confirmed.',
        ];
    }
}
