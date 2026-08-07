<?php

namespace App\Modules\Commerce\Application\Services;

/**
 * RedirectPaymentGatewayInterface::initiate()'s return shape. An
 * Application-layer supporting type (not a Domain VO), the same role
 * PaymentGatewayResult already plays for the synchronous charge() path.
 */
final class PaymentInitiationResult
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public readonly string $redirectUrl,
        public readonly string $providerReference,
        public readonly array $rawResponse,
    ) {
    }
}
