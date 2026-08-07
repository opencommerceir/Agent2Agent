<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use App\Modules\Commerce\Domain\Exceptions\PaymentSessionNotFoundException;
use App\Modules\Commerce\Domain\Repositories\PaymentSessionRepositoryInterface;

/**
 * A read-only status check — never mutates the `PaymentSession`, unlike
 * `ConfirmRedirectPaymentAction` (matches "استعلام" being explicitly a
 * status check, not a confirmation, in Zibal's own docs, §7.37). Backs
 * `commerce.payment.inquiry`, tenant-scoped only — this Action has no
 * public/unauthenticated caller, unlike `ConfirmRedirectPaymentAction`.
 */
final class InquirePaymentAction
{
    public function __construct(
        private readonly PaymentSessionRepositoryInterface $sessions,
        private readonly PaymentGatewayRegistry $gateways,
    ) {
    }

    /**
     * @return array{tracking_reference: int, gateway: string, session_status: string, gateway_successful: ?bool, gateway_response: ?array<string, mixed>}
     */
    public function execute(int $sessionId, int $tenantId): array
    {
        $session = $this->sessions->findById($sessionId, $tenantId);

        if (! $session) {
            throw new PaymentSessionNotFoundException("Payment session [{$sessionId}] does not exist.");
        }

        $result = $session->providerReference() !== null
            ? $this->gateways->get($session->gateway())->inquiry($session->providerReference())
            : null;

        return [
            'tracking_reference' => $session->id(),
            'gateway' => $session->gateway(),
            'session_status' => $session->status()->value,
            'gateway_successful' => $result?->successful,
            'gateway_response' => $result?->rawResponse,
        ];
    }
}
